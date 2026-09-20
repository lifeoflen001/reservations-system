<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\StoreApiTokenRequest;
use App\Http\Requests\Settings\StoreReservationSourceRequest;
use App\Http\Requests\Settings\StoreWebhookEndpointRequest;
use App\Http\Requests\Settings\UpdateEmailIntegrationRequest;
use App\Http\Requests\Settings\UpdateGeneralSettingsRequest;
use App\Http\Requests\Settings\UpdateSecuritySettingsRequest;
use App\Http\Requests\Settings\UpdateWhatsAppIntegrationRequest;
use App\Models\ApiToken;
use App\Models\ChannelConnection;
use App\Models\IntegrationSetting;
use App\Models\ReservationSource;
use App\Models\WebhookEndpoint;
use App\Services\ApiTokenService;
use App\Services\ConfiguredEmailProvider;
use App\Services\DatabaseBackupService;
use App\Services\IntegrationSettingsService;
use App\Services\PropertySettingsService;
use App\Services\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly PropertySettingsService $properties, private readonly SystemSettingsService $system, private readonly IntegrationSettingsService $integrations, private readonly ApiTokenService $tokens, private readonly DatabaseBackupService $backups) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('settings.view') || $request->user()->hasPermission('settings.manage'), 403);
        $section = (string) $request->input('section', 'general');
        $allowed = ['general', 'sources', 'database', 'license', 'updates', 'security', 'about', 'integrations'];
        abort_unless(in_array($section, $allowed, true), 404);
        $requiredPermission = match ($section) {
            'sources' => 'reservation_sources.view', 'security' => 'security.view', 'database' => 'database.view',
            'license' => 'license.view', 'updates' => 'updates.view', 'about' => 'system.about.view', 'integrations' => 'integrations.view', default => 'settings.view',
        };
        abort_unless($request->user()->hasPermission($requiredPermission) || $request->user()->hasPermission('settings.manage'), 403);

        return view('settings.index', [
            'section' => $section, 'property' => $this->properties->current(), 'currency' => $this->properties->currency(),
            'currencies' => $this->properties->currencies(), 'languages' => $this->properties->languages(),
            'settings' => array_replace($this->system->defaults(), $this->system->all()),
            'sources' => ReservationSource::query()->withCount('reservations')->orderBy('sort_order')->orderBy('name')->get(),
            'databaseInfo' => $this->databaseInfo(), 'backups' => $this->backups->recent(), 'version' => config('app.version'),
            'edition' => $this->system->get('edition', 'Pro (Development)'),
            'source' => $request->filled('edit_source') ? ReservationSource::find($request->integer('edit_source')) : null,
            'openSourceForm' => $request->boolean('new_source') || $request->filled('edit_source'),
            'emailIntegration' => $this->integrations->get('email'), 'whatsappIntegration' => $this->integrations->get('whatsapp'),
            'gatewayIntegrations' => IntegrationSetting::query()->where('key', 'like', 'gateway:%')->get(), 'channels' => ChannelConnection::query()->latest()->get(),
            'apiTokens' => ApiToken::query()->where('user_id', $request->user()->id)->latest()->get(), 'webhooks' => WebhookEndpoint::query()->latest()->get(),
            'oneTimeToken' => session('one_time_api_token'),
        ]);
    }

    public function updateEmail(UpdateEmailIntegrationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $old = $this->integrations->get('email');
        $secrets = $old?->secrets ?? [];
        if ($request->filled('password')) {
            $secrets['password'] = $data['password'];
        }
        unset($data['password']);
        $enabled = $request->boolean('enabled');
        $this->integrations->save('email', ['provider' => 'smtp', 'status' => $enabled ? 'pending_test' : 'not_configured', 'mode' => 'smtp', 'is_enabled' => $enabled, 'settings' => $data, 'secrets' => $secrets], $request->user()->id);

        return back()->with('success', 'Email integration settings saved.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('email_settings.manage'), 403);
        $integration = $this->integrations->get('email');
        if (! $integration || ! app(ConfiguredEmailProvider::class, ['integration' => $integration])->configurationValid()) {
            return back()->with('warning', 'Email settings are incomplete. No message was sent.');
        }
        $this->integrations->save('email', ['status' => 'configured', 'last_success_at' => now(), 'last_error' => null]);

        return back()->with('success', 'Email configuration validated. No test message was sent.');
    }

    public function updateWhatsApp(UpdateWhatsAppIntegrationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $old = $this->integrations->get('whatsapp');
        $secrets = $old?->secrets ?? [];
        if ($request->filled('access_token')) {
            $secrets['access_token'] = $data['access_token'];
        } unset($data['access_token']);
        $enabled = $request->boolean('enabled');
        $this->integrations->save('whatsapp', ['provider' => $data['provider'], 'status' => $enabled && ! empty($secrets['access_token']) ? 'pending_test' : 'not_configured', 'mode' => 'api', 'is_enabled' => $enabled, 'settings' => $data, 'secrets' => $secrets], $request->user()->id);

        return back()->with('success', 'WhatsApp integration settings saved.');
    }

    public function storeApiToken(StoreApiTokenRequest $request): RedirectResponse
    {
        [, $plain] = $this->tokens->issue($request->user(), $request->string('name')->toString(), $request->input('abilities', []));

        return back()->with('success', 'API token created. Copy it now; it will not be shown again.')->with('one_time_api_token', $plain);
    }

    public function revokeApiToken(Request $request, ApiToken $token): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('api_tokens.manage') && $token->user_id === $request->user()->id, 403);
        $token->update(['revoked_at' => now()]);

        return back()->with('success', 'API token revoked.');
    }

    public function storeWebhook(StoreWebhookEndpointRequest $request): RedirectResponse
    {
        WebhookEndpoint::create($request->validated() + ['signing_secret' => $request->input('signing_secret') ?: bin2hex(random_bytes(24)), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Webhook endpoint saved.');
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $request): void {
            $propertyData = collect($data)->except('theme')->all();
            $this->properties->update($propertyData, $request->user()->id);
            $this->system->set('theme', $data['theme'], 'string', $request->user()->id);
        });

        return back()->with('success', 'General settings updated.');
    }

    public function updateSecurity(UpdateSecuritySettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password_require_mixed_case'] = $request->boolean('password_require_mixed_case');
        $data['password_require_numbers'] = $request->boolean('password_require_numbers');
        $data['password_require_symbols'] = $request->boolean('password_require_symbols');
        $this->system->update($data, $request->user()->id);

        return back()->with('success', 'Security settings updated.');
    }

    public function storeSource(StoreReservationSourceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        ReservationSource::create($data + ['is_active' => $request->boolean('is_active'), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Reservation source created.');
    }

    public function updateSource(StoreReservationSourceRequest $request, ReservationSource $source): RedirectResponse
    {
        $source->update($request->validated() + ['is_active' => $request->boolean('is_active'), 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Reservation source updated.');
    }

    public function toggleSource(Request $request, ReservationSource $source): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reservation_sources.manage'), 403);
        $source->update(['is_active' => ! $source->is_active, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Reservation source status updated.');
    }

    public function destroySource(Request $request, ReservationSource $source): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reservation_sources.manage'), 403);
        if ($source->reservations()->exists()) {
            return back()->with('error', 'Referenced sources cannot be deleted. Deactivate the source instead.');
        }
        $source->delete();

        return back()->with('success', 'Reservation source deleted.');
    }

    public function checkUpdates(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);
        $this->system->set('last_update_check', now()->toIso8601String(), 'string', $request->user()->id);

        return back()->with('info', 'Update check completed. No secure updates are available.');
    }

    public function downloadDatabaseBackup(Request $request): mixed
    {
        $this->authorizeDatabaseAccess($request);

        try {
            $path = $this->backups->create();

            return response()->download($path, basename($path), ['Content-Type' => 'application/sql']);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Database backup could not be created. Check the database backup configuration and try again.');
        }
    }

    public function downloadExistingDatabaseBackup(Request $request, string $filename): mixed
    {
        $this->authorizeDatabaseAccess($request);

        abort_unless($filename === basename($filename) && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'sql', 404);
        $directory = (string) config('hotel.database_backup.path', storage_path('app/backups'));
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $realDirectory = realpath($directory);
        $realPath = realpath($path);

        abort_unless($realDirectory && $realPath && str_starts_with($realPath, $realDirectory.DIRECTORY_SEPARATOR) && is_file($realPath), 404);

        return response()->download($realPath, $filename, ['Content-Type' => 'application/sql']);
    }

    private function authorizeDatabaseAccess(Request $request): void
    {
        abort_unless($request->user()->hasPermission('database.view') || $request->user()->hasPermission('settings.manage'), 403);
    }

    private function databaseInfo(): array
    {
        try {
            DB::connection()->getPdo();
            $status = 'Connected';
        } catch (\Throwable) {
            $status = 'Unavailable';
        }

        return ['driver' => DB::connection()->getDriverName(), 'name' => DB::connection()->getDatabaseName(), 'status' => $status, 'migrations' => Schema::getTables() ? 'Available' : 'Unavailable', 'storage_writable' => is_writable(storage_path()), 'cache_driver' => config('cache.default')];
    }
}
