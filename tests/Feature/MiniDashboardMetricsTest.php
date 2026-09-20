<?php

namespace Tests\Feature;

use App\Enums\HousekeepingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Department;
use App\Models\Floor;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTask;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Models\User;
use App\Services\MiniDashboardMetricsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiniDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_staff_metrics_use_active_departments_and_login_history(): void
    {
        Department::create(['name' => 'Front Desk', 'is_active' => true]);
        Department::create(['name' => 'Maintenance', 'is_active' => false]);
        User::factory()->create(['is_active' => true, 'last_login_at' => now()->subHours(4)]);
        User::factory()->create(['is_active' => false, 'last_login_at' => now()->subDays(3)]);

        $metrics = app(MiniDashboardMetricsService::class)->staff();

        $this->assertSame(2, $this->metric($metrics, 'Total staff')['value']);
        $this->assertSame(1, $this->metric($metrics, 'Active staff')['value']);
        $this->assertSame(1, $this->metric($metrics, 'Departments')['value']);
        $this->assertSame(1, $this->metric($metrics, 'Recently active')['value']);
    }

    public function test_housekeeping_metrics_respect_status_and_property_day(): void
    {
        [, $room] = $this->guestContext();
        HousekeepingTask::create(['room_id' => $room->id, 'task_type' => 'cleaning', 'priority' => TaskPriority::Normal, 'status' => TaskStatus::Pending]);
        HousekeepingTask::create(['room_id' => $room->id, 'task_type' => 'inspection', 'priority' => TaskPriority::High, 'status' => TaskStatus::InProgress]);
        HousekeepingTask::create(['room_id' => $room->id, 'task_type' => 'cleaning', 'priority' => TaskPriority::Normal, 'status' => TaskStatus::Completed, 'completed_at' => now()]);
        HousekeepingTask::create(['room_id' => $room->id, 'task_type' => 'cleaning', 'priority' => TaskPriority::Normal, 'status' => TaskStatus::Completed, 'completed_at' => now()->subDay()]);

        $metrics = app(MiniDashboardMetricsService::class)->housekeeping();

        $this->assertSame(3, $this->metric($metrics, 'Total tasks')['value']);
        $this->assertSame(1, $this->metric($metrics, 'Pending cleaning')['value']);
        $this->assertSame(1, $this->metric($metrics, 'In progress')['value']);
        $this->assertSame(1, $this->metric($metrics, 'Completed today')['value']);
    }

    public function test_maintenance_metrics_exclude_closed_work_from_open_and_overdue(): void
    {
        MaintenanceTask::create(['issue' => 'Open normal', 'priority' => TaskPriority::Normal, 'status' => TaskStatus::Pending]);
        MaintenanceTask::create(['issue' => 'Active urgent', 'priority' => TaskPriority::Urgent, 'status' => TaskStatus::InProgress]);
        MaintenanceTask::create(['issue' => 'Closed overdue', 'priority' => TaskPriority::High, 'status' => TaskStatus::Completed, 'due_at' => now()->subDay()]);
        MaintenanceTask::create(['issue' => 'Open overdue', 'priority' => TaskPriority::High, 'status' => TaskStatus::Pending, 'due_at' => now()->subDay()]);

        $metrics = app(MiniDashboardMetricsService::class)->maintenance();

        $this->assertSame(3, $this->metric($metrics, 'Open issues')['value']);
        $this->assertSame(1, $this->metric($metrics, 'In progress')['value']);
        $this->assertSame(2, $this->metric($metrics, 'High priority')['value']);
        $this->assertSame(1, $this->metric($metrics, 'Overdue')['value']);
    }

    public function test_payment_metrics_count_only_successful_financial_activity(): void
    {
        [$client, $room] = $this->guestContext();
        $reservation = Reservation::create(['code' => 'RSV-1001', 'client_id' => $client->id, 'room_id' => $room->id, 'check_in' => now()->subDay(), 'check_out' => now()->addDay(), 'total_amount' => 300, 'status' => ReservationStatus::CheckedIn]);
        Payment::create(['invoice_number' => 'INV-1001', 'reservation_id' => $reservation->id, 'client_id' => $client->id, 'amount' => 100, 'method' => 'cash', 'transaction_date' => now(), 'status' => PaymentStatus::Paid]);
        Payment::create(['invoice_number' => 'INV-1002', 'reservation_id' => $reservation->id, 'client_id' => $client->id, 'amount' => 50, 'method' => 'card', 'transaction_date' => now()->subDay(), 'status' => PaymentStatus::Paid]);
        Payment::create(['invoice_number' => 'INV-1003', 'reservation_id' => $reservation->id, 'client_id' => $client->id, 'amount' => 40, 'method' => 'cash', 'transaction_date' => now(), 'status' => PaymentStatus::Voided]);
        Payment::create(['invoice_number' => 'INV-1004', 'reservation_id' => $reservation->id, 'client_id' => $client->id, 'amount' => 30, 'method' => 'cash', 'transaction_date' => now(), 'status' => PaymentStatus::Pending]);

        $metrics = app(MiniDashboardMetricsService::class)->payments();

        $this->assertStringContainsString('150.00', $this->metric($metrics, 'Total collected')['value']);
        $this->assertStringContainsString('100.00', $this->metric($metrics, "Today's payments")['value']);
        $this->assertStringContainsString('150.00', $this->metric($metrics, 'Outstanding')['value']);
        $this->assertSame(2, $this->metric($metrics, 'Transactions')['value']);
    }

    public function test_client_metrics_count_unique_current_future_and_returning_guests(): void
    {
        [$client, $room] = $this->guestContext();
        $futureClient = Client::create(['first_name' => 'Future', 'last_name' => 'Guest']);
        $returning = Client::create(['first_name' => 'Returning', 'last_name' => 'Guest']);
        $this->reservation('RSV-2001', $client, $room, ReservationStatus::CheckedIn, now()->subDay(), now()->addDay());
        $this->reservation('RSV-2002', $futureClient, $room, ReservationStatus::Confirmed, now()->addDays(2), now()->addDays(3));
        $this->reservation('RSV-2003', $returning, $room, ReservationStatus::CheckedOut, now()->subDays(5), now()->subDays(3));
        $this->reservation('RSV-2004', $returning, $room, ReservationStatus::CheckedOut, now()->subDays(10), now()->subDays(8));

        $metrics = app(MiniDashboardMetricsService::class)->clients();

        $this->assertSame(3, $this->metric($metrics, 'Total clients')['value']);
        $this->assertSame(1, $this->metric($metrics, 'In-house guests')['value']);
        $this->assertSame(1, $this->metric($metrics, 'Upcoming guests')['value']);
        $this->assertSame(1, $this->metric($metrics, 'Returning guests')['value']);
    }

    public function test_reservation_metrics_use_valid_statuses_and_property_day(): void
    {
        [$client, $room] = $this->guestContext();
        $this->reservation('RSV-3001', $client, $room, ReservationStatus::Confirmed, now(), now()->addDay());
        $this->reservation('RSV-3002', $client, $room, ReservationStatus::CheckedIn, now()->subDay(), now());
        $this->reservation('RSV-3003', $client, $room, ReservationStatus::CheckedOut, now()->subDays(3), now()->subDay());
        $this->reservation('RSV-3004', $client, $room, ReservationStatus::Cancelled, now(), now()->addDay());

        $metrics = app(MiniDashboardMetricsService::class)->reservations();

        $this->assertSame(3, $this->metric($metrics, 'Total reservations')['value']);
        $this->assertSame(1, $this->metric($metrics, 'Arrivals today')['value']);
        $this->assertSame(1, $this->metric($metrics, 'In-house')['value']);
        $this->assertSame(1, $this->metric($metrics, 'Departures today')['value']);
    }

    private function metric(array $metrics, string $label): array
    {
        return collect($metrics)->firstWhere('label', $label);
    }

    /** @return array{0: Client, 1: Room} */
    private function guestContext(): array
    {
        $floor = Floor::create(['name' => 'Floor 1']);
        $category = RoomCategory::create(['name' => 'Standard']);
        $type = RoomType::create(['name' => 'Standard Room', 'capacity' => 2, 'base_rate' => 100]);
        $room = Room::create(['room_number' => (string) random_int(100, 999), 'floor_id' => $floor->id, 'room_category_id' => $category->id, 'room_type_id' => $type->id, 'operational_status' => RoomOperationalStatus::Available, 'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => 100, 'capacity' => 2]);
        return [Client::create(['first_name' => 'Test', 'last_name' => 'Guest']), $room];
    }

    private function reservation(string $code, Client $client, Room $room, ReservationStatus $status, Carbon $checkIn, Carbon $checkOut): Reservation
    {
        return Reservation::create(['code' => $code, 'client_id' => $client->id, 'room_id' => $room->id, 'check_in' => $checkIn, 'check_out' => $checkOut, 'total_amount' => 100, 'status' => $status]);
    }
}
