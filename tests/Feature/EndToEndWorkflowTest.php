<?php

namespace Tests\Feature;

use App\Enums\HousekeepingStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Floor;
use App\Models\HousekeepingTask;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Reservation;
use App\Models\ReservationSequence;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Models\User;
use App\Services\HousekeepingService;
use App\Services\HotelAnalyticsService;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\ReservationWorkflowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EndToEndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_front_desk_workflow_reaches_financial_and_operational_completion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', config('app.timezone')));

        try {
            Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]);
            PaymentMethod::create(['code' => 'cash', 'name' => 'Cash', 'sort_order' => 1, 'is_active' => true]);
            ReservationSequence::create(['id' => 1, 'next_number' => 1]);

            $user = $this->administrator();
            $clientResponse = $this->actingAs($user)->post(route('clients.store'), [
                'first_name' => 'End', 'last_name' => 'Guest', 'email' => 'end-to-end@example.com', 'country' => 'TZ',
            ]);
            $clientResponse->assertRedirect();
            $client = Client::query()->where('email', 'end-to-end@example.com')->firstOrFail();

            $room = $this->room('401');
            $this->actingAs($user)->get(route('rooms.index', ['tab' => 'map']))->assertOk()->assertSee('401');
            $this->actingAs($user)->get(route('housekeeping.index', ['new' => 1]))->assertOk()->assertSee('401');
            $this->actingAs($user)->get(route('maintenance.index', ['new' => 1]))->assertOk()->assertSee('401');

            $reservation = app(ReservationService::class)->create([
                'client_id' => $client->id,
                'room_id' => $room->id,
                'check_in' => '2026-10-01 14:00',
                'check_out' => '2026-10-04 11:00',
                'adults' => 2,
                'children' => 0,
                'nightly_rate' => 100,
                'status' => ReservationStatus::Confirmed->value,
            ], $user->id);

            $this->actingAs($user)->get(route('reservations.index', ['search' => $reservation->code]))
                ->assertOk()->assertSee($reservation->code)->assertSee('End Guest');

            $deposit = app(PaymentService::class)->post($reservation, [
                'amount' => 100, 'method' => 'cash', 'transaction_date' => '2026-10-01 10:00',
            ], $user->id);
            $this->assertSame(200.0, $reservation->fresh()->balance());

            app(ReservationWorkflowService::class)->checkIn($reservation, $user->id);
            $this->assertSame(ReservationStatus::CheckedIn, $reservation->fresh()->status);
            $this->assertSame(RoomOperationalStatus::Occupied, $room->fresh()->operational_status);

            app(ReservationWorkflowService::class)->checkOut($reservation, $user->id);
            $this->assertSame(ReservationStatus::CheckedOut, $reservation->fresh()->status);
            $this->assertSame(RoomOperationalStatus::MustClean, $room->fresh()->operational_status);
            $task = HousekeepingTask::query()->where('room_id', $room->id)->where('status', TaskStatus::Pending->value)->firstOrFail();

            app(HousekeepingService::class)->complete($task, $user->id);
            $this->assertSame(HousekeepingStatus::Clean, $room->fresh()->housekeeping_status);
            $this->assertSame(RoomOperationalStatus::Available, $room->fresh()->operational_status);

            $finalPayment = app(PaymentService::class)->post($reservation, [
                'amount' => 200, 'method' => 'cash', 'transaction_date' => '2026-10-04 10:00',
            ], $user->id);
            $this->assertSame(0.0, $reservation->fresh()->balance());

            $this->actingAs($user)->get(route('invoices.show', $deposit->invoice))->assertOk()->assertSee($deposit->invoice_number);
            $this->actingAs($user)->get(route('invoices.download', $finalPayment->invoice))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->actingAs($user)->get(route('clients.index', ['search' => 'end-to-end@example.com']))->assertOk()->assertSee('End Guest');

            Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00', config('app.timezone')));
            $dashboard = app(HotelAnalyticsService::class)->dashboard(includeFinancial: true);
            $this->assertSame('$200.00', $dashboard['metrics'][4]['value']);
            $this->assertSame('$0.00', $dashboard['metrics'][5]['value']);

            $report = app(HotelAnalyticsService::class)->report(
                Carbon::parse('2026-10-01', config('app.timezone')),
                Carbon::parse('2026-10-04', config('app.timezone')),
            );
            $this->assertSame(3, $report['roomNights']);
            $this->assertSame(4, $report['availableRoomNights']);
            $this->assertSame(75.0, $report['occupancy']);
            $this->assertSame(100.0, $report['adr']);
            $this->assertSame(75.0, $report['revpar']);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function administrator(): User
    {
        $permissions = collect(config('hotel.permissions'))->map(fn (string $name) => Permission::create(['name' => $name, 'label' => $name]));
        $role = Role::create(['name' => 'e2e-administrator', 'label' => 'E2E Administrator', 'is_active' => true]);
        $role->permissions()->sync($permissions->pluck('id')->all());

        return User::create([
            'name' => 'E2E Administrator', 'first_name' => 'E2E', 'last_name' => 'Administrator',
            'username' => 'e2e-admin', 'email' => 'e2e-admin@example.com', 'password' => Hash::make('secret'),
            'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function room(string $number): Room
    {
        $floor = Floor::create(['name' => 'Floor 4']);
        $category = RoomCategory::create(['name' => 'Suite']);
        $type = RoomType::create(['name' => 'Suite Room', 'capacity' => 4, 'base_rate' => 100]);

        return Room::create([
            'room_number' => $number, 'floor_id' => $floor->id, 'room_category_id' => $category->id,
            'room_type_id' => $type->id, 'operational_status' => RoomOperationalStatus::Available,
            'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => 100, 'capacity' => 4, 'is_active' => true,
        ]);
    }
}
