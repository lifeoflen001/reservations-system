<?php

namespace Tests\Feature;

use App\Enums\HousekeepingStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Models\Client;
use App\Models\Floor;
use App\Models\Installation;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationSource;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsAndSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_run_setup_completes_once_and_gates_reentry(): void
    {
        $this->get(route('login'))->assertRedirect(route('setup.index'));
        $this->get(route('setup.index'))->assertOk()->assertSee('HotelDesk setup');
        $this->post(route('setup.operating-mode'), ['operating_mode' => 'desktop'])->assertRedirect(route('setup.index'));

        $response = $this->post(route('setup.complete'), [
            'full_name' => 'Setup Administrator',
            'email' => 'setup@example.com',
            'username' => 'setup-admin',
            'password' => 'SetupPass123!',
            'password_confirmation' => 'SetupPass123!',
            'operating_mode' => 'desktop',
            'property_name' => 'Harbor View Hotel',
            'property_email' => 'frontdesk@example.com',
            'property_phone' => '+1 202 555 0110',
            'property_address' => '100 Ocean Avenue',
            'property_city' => 'Miami',
            'property_country' => 'United States',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'language' => 'en',
        ]);

        $response->assertRedirect(route('setup.finish'));
        $this->assertDatabaseHas('users', ['username' => 'setup-admin', 'email' => 'setup@example.com']);
        $this->assertDatabaseHas('properties', ['name' => 'Harbor View Hotel', 'timezone' => 'UTC']);
        $this->assertTrue(Installation::firstOrFail()->isComplete());
        $this->assertTrue(Hash::check('SetupPass123!', User::where('username', 'setup-admin')->firstOrFail()->password));
        $this->get(route('setup.index'))->assertRedirect(route('login'));
    }

    public function test_general_and_security_settings_are_persisted_with_currency_locked(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();
        $originalCurrency = $admin->role?->name ? Property::firstOrFail()->base_currency_id : null;

        $this->actingAs($admin)->put(route('settings.general.update'), [
            'name' => 'Updated Harbor View',
            'email' => 'updated@example.com',
            'phone' => '+1 202 555 0199',
            'address' => '200 Bay Street',
            'city' => 'Miami',
            'country' => 'United States',
            'timezone' => 'UTC',
            'default_language' => 'en',
            'theme' => 'dark',
            'check_in_time' => '15:00',
            'check_out_time' => '10:00',
            'currency' => 'EUR',
        ])->assertRedirect();

        $property = Property::firstOrFail();
        $this->assertSame('Updated Harbor View', $property->name);
        $this->assertSame('UTC', $property->timezone);
        $this->assertSame('15:00', substr($property->check_in_time, 0, 5));
        $this->assertSame($originalCurrency, $property->base_currency_id);
        $this->assertSame('dark', app(SystemSettingsService::class)->get('theme'));

        $this->actingAs($admin)->put(route('settings.security.update'), [
            'session_timeout' => 45,
            'password_min_length' => 10,
            'password_require_numbers' => true,
            'password_require_symbols' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'session_timeout', 'value' => '45']);
        $this->assertDatabaseHas('system_settings', ['key' => 'password_min_length', 'value' => '10']);
        $this->assertSame('1', SystemSetting::where('key', 'password_require_numbers')->value('value'));
    }

    public function test_reservation_sources_can_be_managed_but_referenced_sources_cannot_be_deleted(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();
        $source = ReservationSource::create(['name' => 'Partner Portal', 'code' => 'partner-portal', 'is_active' => true]);

        $this->actingAs($admin)->post(route('settings.sources.toggle', $source))->assertRedirect();
        $this->assertFalse($source->fresh()->is_active);

        $client = Client::create(['first_name' => 'Source', 'last_name' => 'Guest', 'email' => 'source@example.com']);
        $floor = Floor::create(['name' => 'Source Test Floor']);
        $category = RoomCategory::create(['name' => 'Source Test Category']);
        $type = RoomType::create(['name' => 'Source Test Room', 'capacity' => 2, 'base_rate' => 100]);
        $room = Room::create([
            'room_number' => '999', 'floor_id' => $floor->id, 'room_category_id' => $category->id,
            'room_type_id' => $type->id, 'operational_status' => RoomOperationalStatus::Available,
            'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => 100, 'capacity' => 2,
        ]);
        Reservation::create([
            'code' => 'SRC-0001', 'client_id' => $client->id, 'room_id' => $room->id,
            'reservation_source_id' => $source->id, 'created_by' => $admin->id,
            'check_in' => '2026-11-01 14:00', 'check_out' => '2026-11-03 11:00',
            'adults' => 1, 'children' => 0, 'nightly_rate' => 100, 'total_amount' => 200,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->actingAs($admin)->delete(route('settings.sources.destroy', $source))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseHas('reservation_sources', ['id' => $source->id]);

        $freeSource = ReservationSource::create(['name' => 'Temporary Source', 'code' => 'temporary-source', 'is_active' => true]);
        $this->actingAs($admin)->delete(route('settings.sources.destroy', $freeSource))->assertRedirect();
        $this->assertDatabaseMissing('reservation_sources', ['id' => $freeSource->id]);
    }
}
