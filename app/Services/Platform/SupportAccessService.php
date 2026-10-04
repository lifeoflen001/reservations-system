<?php

namespace App\Services\Platform;

use App\Models\Organization;
use App\Models\PlatformAdministrator;
use App\Models\PlatformSupportSession;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SupportAccessService
{
    public const SESSION_KEY = 'platform.support_session_id';

    public function __construct(private readonly PlatformAuditService $audit) {}

    public function start(PlatformAdministrator $administrator, Organization $organization, ?Property $property, string $reason, int $minutes, Request $request): PlatformSupportSession
    {
        if ($organization->status !== 'active') {
            throw ValidationException::withMessages(['organization_id' => 'Support access requires an active organization.']);
        }
        if ($property && (int) $property->organization_id !== (int) $organization->getKey()) {
            throw ValidationException::withMessages(['property_id' => 'The selected property does not belong to this organization.']);
        }
        $maxMinutes = (int) config('platform.support.max_minutes', 60);
        $allowedDurations = array_map('intval', (array) config('platform.support.allowed_durations', [15, 30, 60]));
        if ($minutes < 5 || $minutes > $maxMinutes || ! in_array($minutes, $allowedDurations, true)) {
            throw ValidationException::withMessages(['duration' => 'Choose a bounded support duration of 15, 30 or 60 minutes.']);
        }

        $session = PlatformSupportSession::create([
            'uuid' => (string) Str::uuid(),
            'platform_administrator_id' => $administrator->getKey(),
            'organization_id' => $organization->getKey(),
            'property_id' => $property?->getKey(),
            'reason' => trim($reason),
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addMinutes($minutes),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        $request->session()->put(self::SESSION_KEY, $session->getKey());
        $this->audit->record($administrator, 'support.session_started', $session, ['duration_minutes' => $minutes], $organization->getKey(), $property?->getKey(), $request);

        return $session->load(['organization', 'property']);
    }

    public function current(PlatformAdministrator $administrator, ?Request $request = null): ?PlatformSupportSession
    {
        $request ??= app(Request::class);
        $id = $request->session()->get(self::SESSION_KEY);
        if (! $id) return null;

        $session = PlatformSupportSession::query()->with(['organization', 'property'])->whereKey($id)->where('platform_administrator_id', $administrator->getKey())->first();
        if (! $session) {
            $request->session()->forget(self::SESSION_KEY);
            return null;
        }
        if ($session->isActive()) return $session;

        if ($session->status === 'active') {
            $session->forceFill(['status' => 'expired', 'ended_at' => now()])->save();
            $this->audit->record($administrator, 'support.session_expired', $session, [], $session->organization_id, $session->property_id, $request);
        }
        $request->session()->forget(self::SESSION_KEY);
        return null;
    }

    public function enter(PlatformAdministrator $administrator, PlatformSupportSession $session, Request $request): PlatformSupportSession
    {
        $this->assertOwnedActive($administrator, $session);
        $request->session()->put(self::SESSION_KEY, $session->getKey());
        $this->audit->record($administrator, 'support.session_entered', $session, [], $session->organization_id, $session->property_id, $request);
        return $session->load(['organization', 'property']);
    }

    public function end(PlatformAdministrator $administrator, ?PlatformSupportSession $session, Request $request): void
    {
        if (! $session || (int) $session->platform_administrator_id !== (int) $administrator->getKey()) {
            $request->session()->forget(self::SESSION_KEY);
            return;
        }
        if ($session->status === 'active') {
            $session->forceFill(['status' => 'ended', 'ended_at' => now(), 'ended_by' => $administrator->getKey()])->save();
            $this->audit->record($administrator, 'support.session_ended', $session, [], $session->organization_id, $session->property_id, $request);
        }
        $request->session()->forget(self::SESSION_KEY);
    }

    public function assertOwnedActive(PlatformAdministrator $administrator, PlatformSupportSession $session): void
    {
        abort_unless((int) $session->platform_administrator_id === (int) $administrator->getKey(), 404);
        abort_unless($session->isActive(), 410, 'This support session has expired.');
    }
}
