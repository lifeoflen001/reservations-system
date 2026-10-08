<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Installation;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Subscription;
use App\Models\Role;
use App\Models\User;
use App\Services\SystemSettingsService;
use App\Services\SaasDefaultTenantBackfillService;
use App\Services\OperationalTenantBackfillService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Establish the legacy single-property anchor before seeders create
        // tenant-owned catalog and finance records. Ownership is still
        // assigned by model events, never by browser input.
        $currency = Currency::firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true,
        ]);
        $property = Property::query()->orderBy('id')->first();
        // Existing installations already have a property-backed organization;
        // keep that relationship as the seed anchor instead of creating a
        // second organization and trying to activate it with the old property.
        $organization = $property?->organization;
        if (! $organization) {
            $organization = Organization::firstOrCreate(['slug' => 'lodgix-default-organization'], [
                'uuid' => (string) Str::uuid(), 'name' => config('hotel.defaults.property_name', 'Lodgix Organization'),
                'status' => 'active', 'subscription_status' => 'active',
            ]);
        }
        if (! $property) {
            $property = Property::create([
                'organization_id' => $organization->id,
                'name' => config('hotel.defaults.property_name'), 'default_language' => 'en',
                'check_in_time' => config('hotel.defaults.check_in_time'), 'check_out_time' => config('hotel.defaults.check_out_time'),
                'timezone' => config('hotel.defaults.timezone'), 'base_currency_id' => $currency->id,
            ]);
        }

        $legacyPlan = Plan::query()->where('code', 'legacy_full_access')->first();
        if ($legacyPlan && ! $organization->subscriptions()->exists()) {
            Subscription::create([
                'organization_id' => $organization->getKey(),
                'plan_id' => $legacyPlan->getKey(),
                'status' => 'active',
                'starts_at' => now(),
            ]);
        }

        // Tenant creation is an explicit installation/backfill operation. All
        // subsequent property-owned seeders run inside that trusted context;
        // model events no longer guess the first active property.
        app(SaasDefaultTenantBackfillService::class)->run();
        $property->refresh();
        app(TenantContext::class)->activate((int) $organization->id, (int) $property->id);

        try {
            $this->call([
                ReferenceDataSeeder::class,
                RbacSeeder::class,
                FinanceReferenceSeeder::class,
                RoomCatalogSeeder::class,
            ]);
            if (app()->environment('local')) {
                $this->call(PosDemoSeeder::class);
            }

            $superAdministratorRole = Role::where('name', 'super_administrator')->firstOrFail();

            $administrator = User::query()
                ->where(fn ($query) => $query->where('email', 'admin@lodgix.test')->orWhere('username', 'admin'))
                ->first();

            // Keep the existing password on normal reseeds. A one-time Railway bootstrap
            // override is available for recovering an installation whose admin password
            // was never seeded correctly.
            if (! $administrator) {
                $administrator = User::create([
                    'name' => 'Lodgix Administrator',
                    'email' => 'admin@lodgix.test',
                    'username' => 'admin',
                    'role_id' => $superAdministratorRole->getKey(),
                    'is_active' => true,
                    'password' => Hash::make(env('ADMIN_RESET_PASSWORD', 'Admin123!')),
                ]);
            } elseif (filled(env('ADMIN_RESET_PASSWORD'))) {
                $administrator->password = Hash::make(env('ADMIN_RESET_PASSWORD'));
                $administrator->save();
            }
        } finally {
            app(TenantContext::class)->release();
        }

        app(SaasDefaultTenantBackfillService::class)->run();
        app(OperationalTenantBackfillService::class)->run();
        $installation = Installation::firstOrCreate([], ['status' => 'unconfigured', 'base_currency_id' => $currency?->id]);
        if ($installation->wasRecentlyCreated) {
            $installation->update(['status' => 'complete', 'base_currency_id' => $currency?->id, 'completed_at' => now()]);
        }
        $settings = app(SystemSettingsService::class);
        foreach ($settings->defaults() as $key => $value) {
            if ($settings->get($key) === null) {
                $settings->set($key, $value);
            }
        }
    }
}
