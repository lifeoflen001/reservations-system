<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Installation;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\SystemSettingsService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            RbacSeeder::class,
            RoomCatalogSeeder::class,
        ]);

        $superAdministratorRole = Role::where('name', 'super_administrator')->firstOrFail();

        $administrator = User::query()
            ->where(fn ($query) => $query->where('email', 'admin@hoteldesk.test')->orWhere('username', 'admin'))
            ->first() ?? new User;
        $administrator->fill([
            'name' => 'HotelDesk Administrator',
            'username' => 'admin',
            'role_id' => $superAdministratorRole->getKey(),
            'is_active' => true,
        ]);

        // Keep the existing password on normal reseeds. A one-time Railway bootstrap
        // override is available for recovering an installation whose admin password
        // was never seeded correctly.
        if (! $administrator->exists) {
            $administrator->email = 'admin@hoteldesk.test';
            $administrator->password = Hash::make(env('ADMIN_RESET_PASSWORD', 'Admin123!'));
        } elseif (filled(env('ADMIN_RESET_PASSWORD'))) {
            $administrator->password = Hash::make(env('ADMIN_RESET_PASSWORD'));
        }

        $administrator->save();

        $currency = Currency::where('code', 'USD')->first();
        Property::firstOrCreate([], [
            'name' => config('hotel.defaults.property_name'), 'default_language' => 'en',
            'check_in_time' => config('hotel.defaults.check_in_time'), 'check_out_time' => config('hotel.defaults.check_out_time'),
            'timezone' => config('hotel.defaults.timezone'), 'base_currency_id' => $currency?->id,
        ]);
        $installation = Installation::firstOrCreate([], ['status' => 'unconfigured', 'base_currency_id' => $currency?->id]);
        if (! $installation->isComplete()) {
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
