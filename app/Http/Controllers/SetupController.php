<?php

namespace App\Http\Controllers;

use App\Http\Requests\Setup\StoreSetupRequest;
use App\Models\Currency;
use App\Models\Installation;
use App\Models\Language;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\InstallationState;
use App\Services\PropertySettingsService;
use App\Services\SystemSettingsService;
use Database\Seeders\RbacSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function __construct(
        private readonly InstallationState $installation,
        private readonly PropertySettingsService $properties,
        private readonly SystemSettingsService $system,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($this->installation->isComplete()) {
            return redirect()->route('login');
        }
        $this->seedReferenceData();
        $step = $request->session()->get('setup.step', 1);

        return view('setup.index', ['step' => min(2, max(1, (int) $step)), 'currencies' => Currency::where('is_active', true)->orderBy('code')->get(), 'languages' => Language::where('is_active', true)->whereIn('code', ['en'])->get(), 'timezones' => \DateTimeZone::listIdentifiers(), 'data' => $request->session()->get('setup.data', [])]);
    }

    public function operatingMode(Request $request): RedirectResponse
    {
        $data = $request->validate(['operating_mode' => ['required', 'in:desktop,web']]);
        $request->session()->put('setup.data', array_merge($request->session()->get('setup.data', []), $data));
        $request->session()->put('setup.step', 2);

        return redirect()->route('setup.index');
    }

    public function complete(StoreSetupRequest $request): RedirectResponse
    {
        abort_if($this->installation->isComplete(), 403, 'Installation is already complete.');
        $data = $request->validated();
        $currency = Currency::where('code', $data['currency'])->where('is_active', true)->firstOrFail();
        $role = Role::where('name', 'super_administrator')->firstOrFail();
        $parts = preg_split('/\s+/', trim($data['full_name']), 2);

        DB::transaction(function () use ($data, $currency, $role, $parts): void {
            $admin = User::create([
                'name' => $data['full_name'], 'first_name' => $parts[0], 'last_name' => $parts[1] ?? null,
                'email' => $data['email'], 'username' => $data['username'], 'password' => Hash::make($data['password']),
                'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false,
            ]);
            Property::updateOrCreate(['id' => 1], [
                'name' => $data['property_name'], 'email' => $data['property_email'] ?? null, 'phone' => $data['property_phone'] ?? null,
                'address' => $data['property_address'] ?? null, 'city' => $data['property_city'] ?? null, 'country' => $data['property_country'] ?? null,
                'base_currency_id' => $currency->id, 'default_language' => $data['language'], 'timezone' => $data['timezone'],
                'check_in_time' => config('hotel.defaults.check_in_time'), 'check_out_time' => config('hotel.defaults.check_out_time'),
                'setup_completed_at' => now(), 'updated_by' => $admin->id,
            ]);
            Installation::updateOrCreate(['id' => 1], ['status' => 'complete', 'operating_mode' => $data['operating_mode'], 'base_currency_id' => $currency->id, 'completed_at' => now()]);
            $this->system->update(['theme' => 'light', 'edition' => 'Pro (Development)', 'last_update_check' => null], $admin->id);
        });

        $this->properties->clearCache();

        $request->session()->forget(['setup.step', 'setup.data']);
        $request->session()->put('setup.completed', ['property' => $data['property_name'], 'administrator' => $data['full_name'], 'currency' => $currency->code, 'language' => $data['language']]);

        return redirect()->route('setup.finish');
    }

    public function finish(Request $request): View|RedirectResponse
    {
        $summary = $request->session()->pull('setup.completed');
        if (! $summary || ! $this->installation->isComplete()) {
            return redirect()->route('login');
        }

        return view('setup.finish', ['summary' => $summary]);
    }

    private function seedReferenceData(): void
    {
        app(ReferenceDataSeeder::class)->run();
        app(RbacSeeder::class)->run();
    }
}
