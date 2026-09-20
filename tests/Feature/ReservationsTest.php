<?php

namespace Tests\Feature;

use App\Enums\HousekeepingStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Floor;
use App\Models\Permission;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Models\User;
use App\Services\ReservationService;
use App\Services\ReservationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_is_created_with_unique_code_calendar_nights_and_total(): void
    {
        $room = $this->room(); $client = $this->client(); $user = $this->user();
        $reservation = $this->makeReservation($room, $client, $user);
        $this->assertMatchesRegularExpression('/^WSX-\d{4}$/', $reservation->code);
        $this->assertSame(3, $reservation->nights());
        $this->assertSame('285.00', $reservation->total_amount);
    }

    public function test_overlapping_reservation_is_rejected_and_same_day_turnover_is_allowed(): void
    {
        $room = $this->room(); $client = $this->client(); $user = $this->user();
        $this->makeReservation($room, $client, $user, '2026-10-10 14:00', '2026-10-12 11:00');
        $this->assertSessionHasErrorsAfterCreate($room, $client, $user, '2026-10-11 14:00', '2026-10-13 11:00');
        $next = $this->makeReservation($room, $client, $user, '2026-10-12 14:00', '2026-10-14 11:00');
        $this->assertNotNull($next->id);
    }

    public function test_edit_excludes_itself_and_capacity_is_enforced(): void
    {
        $room = $this->room(2); $client = $this->client(); $user = $this->user();
        $reservation = $this->makeReservation($room, $client, $user);
        $updated = app(ReservationService::class)->update($reservation, $this->attributes($room, $client, '2026-10-11 14:00', '2026-10-14 11:00', 2), $user->id);
        $this->assertSame(3, $updated->nights());
        $this->expectException(\App\Exceptions\RoomUnavailableException::class);
        app(ReservationService::class)->update($updated, $this->attributes($room, $client, '2026-10-11 14:00', '2026-10-14 11:00', 3), $user->id);
    }

    public function test_check_in_and_check_out_update_room_and_housekeeping(): void
    {
        $room = $this->room(); $client = $this->client(); $user = $this->user();
        $reservation = $this->makeReservation($room, $client, $user);
        app(ReservationWorkflowService::class)->checkIn($reservation, $user->id);
        $this->assertSame(ReservationStatus::CheckedIn, $reservation->fresh()->status);
        $this->assertSame(RoomOperationalStatus::Occupied, $room->fresh()->operational_status);
        app(ReservationWorkflowService::class)->checkOut($reservation, $user->id);
        $this->assertSame(ReservationStatus::CheckedOut, $reservation->fresh()->status);
        $this->assertSame(RoomOperationalStatus::MustClean, $room->fresh()->operational_status);
        $this->assertSame(HousekeepingStatus::Dirty, $room->fresh()->housekeeping_status);
        $this->assertDatabaseHas('housekeeping_tasks', ['room_id' => $room->id, 'status' => TaskStatus::Pending->value]);
    }

    public function test_cancellation_and_no_show_release_availability_and_preserve_history(): void
    {
        $room = $this->room(); $client = $this->client(); $user = $this->user();
        $reservation = $this->makeReservation($room, $client, $user);
        app(ReservationWorkflowService::class)->cancel($reservation, $user->id, 'Guest changed plans');
        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
        $this->assertSame('Guest changed plans', $reservation->fresh()->cancellation_reason);
        $noShow = $this->makeReservation($room, $client, $user, '2026-11-01 14:00', '2026-11-03 11:00');
        app(ReservationWorkflowService::class)->markNoShow($noShow, $user->id);
        $this->assertSame(ReservationStatus::NoShow, $noShow->fresh()->status);
        $this->assertNotNull($noShow->fresh()->no_show_at);
    }

    public function test_invalid_check_in_and_unauthorized_actions_are_rejected(): void
    {
        $room = $this->room(); $client = $this->client(); $admin = $this->user(['reservations.checkin']);
        $pending = $this->makeReservation($room, $client, $admin, status: ReservationStatus::Pending->value);
        try { app(ReservationWorkflowService::class)->checkIn($pending, $admin->id); $this->fail('Pending reservation should not check in.'); }
        catch (\LogicException $exception) { $this->assertStringContainsString('Only confirmed', $exception->getMessage()); }
        $viewer = $this->user(['reservations.view']);
        $this->actingAs($viewer)->post(route('reservations.check-in', $pending))->assertForbidden();
    }

    public function test_reservation_search_and_status_filter_are_database_backed(): void
    {
        $room = $this->room(); $client = $this->client(); $user = $this->user();
        $reservation = $this->makeReservation($room, $client, $user);
        $this->actingAs($user)->get(route('reservations.index', ['search' => $reservation->code]))->assertOk()->assertSee($reservation->code);
        $this->actingAs($user)->get(route('reservations.index', ['status' => 'cancelled']))->assertOk()->assertDontSee($reservation->code);
    }

    public function test_room_planning_returns_overlapping_reservations(): void
    {
        $room = $this->room(); $client = $this->client(); $user = $this->user();
        $reservation = $this->makeReservation($room, $client, $user, '2026-10-10 14:00', '2026-10-12 11:00');
        $this->actingAs($user)->get(route('room-planning.index', ['start' => '2026-10-11', 'days' => 14]))->assertOk()->assertSee($reservation->code)->assertSee($client->full_name);
    }

    public function test_room_planning_supports_date_views_and_room_type_filtering(): void
    {
        $room = $this->room();
        $suiteType = RoomType::create(['name' => 'Executive Suite', 'capacity' => 4, 'base_rate' => 185]);
        $suite = Room::create([
            'room_number' => '999', 'floor_id' => $room->floor_id, 'room_category_id' => $room->room_category_id,
            'room_type_id' => $suiteType->id, 'operational_status' => RoomOperationalStatus::Available,
            'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => 185, 'capacity' => 4,
        ]);
        $user = $this->user();

        $this->actingAs($user)->get(route('room-planning.index', ['view' => 'week', 'start' => '2026-10-01']))
            ->assertOk()->assertSee('999')->assertSee('Standard Room')->assertSee('data-fullscreen-toggle', false);
        $this->actingAs($user)->get(route('room-planning.index', ['view' => 'month', 'start' => '2026-10-01', 'room_type_id' => $suiteType->id]))
            ->assertOk()->assertSee('999')->assertDontSee($room->room_number)->assertSee('01 Oct 2026');
        $this->actingAs($user)->get(route('room-planning.index', ['view' => 'two_months', 'start' => '2026-10-01']))
            ->assertOk()->assertSee('2 Months')->assertSee($suite->room_number);
    }

    public function test_room_planning_uses_category_colors_and_shows_reservation_hover_details(): void
    {
        $floor = Floor::create(['name' => 'Floor 1']);
        $category = RoomCategory::create(['name' => 'Ocean View', 'color' => '#123456']);
        $type = RoomType::create(['name' => 'King Room', 'capacity' => 2, 'base_rate' => 120]);
        $room = Room::create([
            'room_number' => '808', 'floor_id' => $floor->id, 'room_category_id' => $category->id,
            'room_type_id' => $type->id, 'operational_status' => RoomOperationalStatus::Available,
            'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => 120, 'capacity' => 2,
        ]);
        $client = $this->client();
        $user = $this->user();
        $reservation = $this->makeReservation($room, $client, $user, '2026-10-10 14:00', '2026-10-12 11:00');

        $this->actingAs($user)->get(route('room-planning.index', ['view' => 'month', 'start' => '2026-10-01']))
            ->assertOk()
            ->assertSee('--category-color: #123456', false)
            ->assertSee($reservation->code)
            ->assertSee('role="tooltip"', false)
            ->assertSee($client->full_name)
            ->assertSee('2 nights');
    }

    public function test_room_planning_data_endpoint_returns_rooms_reservations_and_blocks_as_json(): void
    {
        $room = $this->room();
        $client = $this->client();
        $user = $this->user();
        $reservation = $this->makeReservation($room, $client, $user, '2026-10-10 14:00', '2026-10-12 11:00');

        $this->actingAs($user)
            ->getJson(route('room-planning.data', ['view' => 'week', 'start' => '2026-10-10']))
            ->assertOk()
            ->assertJsonPath('data.view', 'week')
            ->assertJsonPath('data.days', 7)
            ->assertJsonFragment(['id' => $room->id, 'room_number' => $room->room_number])
            ->assertJsonFragment(['id' => $reservation->id, 'code' => $reservation->code, 'room_id' => $room->id]);
    }

    public function test_room_planning_reservation_filters_and_empty_cells_preserve_booking_context(): void
    {
        $room = $this->room();
        $client = $this->client();
        $user = $this->user();
        $reservation = $this->makeReservation($room, $client, $user, '2026-10-10 14:00', '2026-10-12 11:00');

        $this->actingAs($user)
            ->get(route('room-planning.index', [
                'view' => 'week',
                'start' => '2026-10-10',
                'reservation_search' => $reservation->code,
                'reservation_status' => ReservationStatus::Confirmed->value,
            ]))
            ->assertOk()
            ->assertSee($reservation->code)
            ->assertSee('room_id='.$room->id, false)
            ->assertSee('check_in=2026-10-10', false)
            ->assertSee('check_out=2026-10-11', false);
    }

    private function makeReservation(Room $room, Client $client, User $user, string $checkIn = '2026-10-10 14:00', string $checkOut = '2026-10-13 11:00', int $adults = 2, string $status = 'confirmed'): Reservation
    {
        return app(ReservationService::class)->create($this->attributes($room, $client, $checkIn, $checkOut, $adults, $status), $user->id);
    }

    private function attributes(Room $room, Client $client, string $checkIn, string $checkOut, int $adults = 2, string $status = 'confirmed'): array
    {
        return ['client_id' => $client->id, 'room_id' => $room->id, 'reservation_source_id' => null, 'check_in' => $checkIn, 'check_out' => $checkOut, 'adults' => $adults, 'children' => 0, 'nightly_rate' => 95, 'status' => $status, 'notes' => null];
    }

    private function assertSessionHasErrorsAfterCreate(Room $room, Client $client, User $user, string $checkIn, string $checkOut): void
    {
        $this->actingAs($user)->post(route('reservations.store'), $this->attributes($room, $client, $checkIn, $checkOut))->assertSessionHasErrors('room_id');
    }

    private function room(int $capacity = 4): Room
    {
        $floor = Floor::create(['name' => 'Floor 1']); $category = RoomCategory::create(['name' => 'Standard']); $type = RoomType::create(['name' => 'Standard Room', 'capacity' => $capacity, 'base_rate' => 95]);
        return Room::create(['room_number' => (string) random_int(100, 999), 'floor_id' => $floor->id, 'room_category_id' => $category->id, 'room_type_id' => $type->id, 'operational_status' => RoomOperationalStatus::Available, 'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => 95, 'capacity' => $capacity]);
    }

    private function client(): Client { return Client::create(['first_name' => 'Test', 'last_name' => 'Guest', 'email' => 'guest'.random_int(1, 99999).'@example.com']); }

    private function user(?array $permissions = null): User
    {
        $permissions ??= config('hotel.permissions'); $role = Role::create(['name' => 'role-'.random_int(1, 99999), 'label' => 'Test role']);
        $permissionModels = collect($permissions)->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission], ['label' => $permission])); $role->permissions()->sync($permissionModels->pluck('id'));
        return User::create(['name' => 'Test User', 'username' => 'user'.random_int(1, 99999), 'email' => random_int(1, 99999).'@example.com', 'password' => Hash::make('secret'), 'role_id' => $role->id]);
    }
}
