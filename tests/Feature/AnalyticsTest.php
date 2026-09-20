<?php

namespace Tests\Feature;

use App\Enums\HousekeepingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Models\Client;
use App\Models\Floor;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Services\HotelAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_clips_stays_to_the_selected_period_and_uses_paid_revenue_for_kpis(): void
    {
        $room = $this->room('101');
        $this->room('102');
        $client = Client::create(['first_name' => 'Analytics', 'last_name' => 'Guest', 'email' => 'analytics@example.com']);
        $reservation = Reservation::create([
            'code' => 'WSX-9001',
            'client_id' => $client->id,
            'room_id' => $room->id,
            'check_in' => '2026-09-01 14:00',
            'check_out' => '2026-09-05 11:00',
            'status' => ReservationStatus::Confirmed,
            'nightly_rate' => 100,
            'total_amount' => 400,
        ]);
        Payment::create([
            'invoice_number' => 'INV-9001',
            'reservation_id' => $reservation->id,
            'client_id' => $client->id,
            'amount' => 300,
            'method' => 'cash',
            'reference' => 'PAY-9001',
            'transaction_date' => '2026-09-03 10:00',
            'status' => PaymentStatus::Paid,
        ]);

        $report = app(HotelAnalyticsService::class)->report(
            Carbon::parse('2026-09-02', config('app.timezone')),
            Carbon::parse('2026-09-04', config('app.timezone')),
        );

        $this->assertSame(3, $report['roomNights']);
        $this->assertSame(6, $report['availableRoomNights']);
        $this->assertSame(50.0, $report['occupancy']);
        $this->assertSame(100.0, $report['adr']);
        $this->assertSame(50.0, $report['revpar']);
        $this->assertSame(300.0, $report['revenue']);
        $this->assertSame(1, (int) $report['sourceCounts']->get('Direct'));
        $this->assertSame(300.0, $report['roomTypeRevenue']->get('Standard Room'));
    }

    public function test_dashboard_counts_arrivals_departures_and_sellable_occupancy_from_live_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 12:00:00', config('app.timezone')));

        try {
            $occupied = $this->room('201', RoomOperationalStatus::Occupied);
            $this->room('202', RoomOperationalStatus::Available);
            $this->room('203', RoomOperationalStatus::Maintenance);
            $this->room('204', RoomOperationalStatus::Blocked);
            $client = Client::create(['first_name' => 'Dashboard', 'last_name' => 'Guest', 'email' => 'dashboard@example.com']);

            $priorReservation = Reservation::create([
                'code' => 'WSX-9002', 'client_id' => $client->id, 'room_id' => $occupied->id,
                'check_in' => '2026-09-14 14:00', 'check_out' => '2026-09-16 11:00',
                'status' => ReservationStatus::Confirmed, 'total_amount' => 200,
            ]);
            $priorReservation->forceFill(['created_at' => '2026-09-13 10:00', 'updated_at' => '2026-09-13 10:00'])->saveQuietly();
            Reservation::create([
                'code' => 'WSX-9003', 'client_id' => $client->id, 'room_id' => $occupied->id,
                'check_in' => '2026-09-12 14:00', 'check_out' => '2026-09-14 11:00',
                'status' => ReservationStatus::CheckedOut, 'total_amount' => 200,
                'created_at' => '2026-09-14 09:00', 'updated_at' => '2026-09-14 09:00',
            ]);

            $dashboard = app(HotelAnalyticsService::class)->dashboard(includeFinancial: false);

            $this->assertSame('1', $dashboard['metrics'][0]['value']);
            $this->assertSame('1', $dashboard['metrics'][1]['value']);
            $this->assertSame('1', $dashboard['metrics'][2]['value']);
            $this->assertSame('50%', $dashboard['metrics'][3]['value']);
            $this->assertSame('1/2 Rooms', $dashboard['metrics'][3]['hint']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_report_uses_ten_sellable_rooms_for_the_fifty_percent_occupancy_formula(): void
    {
        $client = Client::create(['first_name' => 'Formula', 'last_name' => 'Guest', 'email' => 'formula@example.com']);

        for ($number = 1; $number <= 10; $number++) {
            $room = $this->room((string) (300 + $number));
            if ($number > 5) {
                continue;
            }

            $reservation = Reservation::create([
                'code' => 'WSX-91'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'client_id' => $client->id,
                'room_id' => $room->id,
                'check_in' => '2026-09-10 14:00',
                'check_out' => '2026-09-11 11:00',
                'status' => ReservationStatus::Confirmed,
                'nightly_rate' => 100,
                'total_amount' => 100,
            ]);
            Payment::create([
                'invoice_number' => 'INV-91'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'reservation_id' => $reservation->id,
                'client_id' => $client->id,
                'amount' => 100,
                'method' => 'cash',
                'reference' => 'PAY-91'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'transaction_date' => '2026-09-10 10:00',
                'status' => PaymentStatus::Paid,
            ]);
        }

        $report = app(HotelAnalyticsService::class)->report(
            Carbon::parse('2026-09-10', config('app.timezone')),
            Carbon::parse('2026-09-10', config('app.timezone')),
        );

        $this->assertSame(5, $report['roomNights']);
        $this->assertSame(10, $report['availableRoomNights']);
        $this->assertSame(50.0, $report['occupancy']);
        $this->assertSame(100.0, $report['adr']);
        $this->assertSame(50.0, $report['revpar']);
    }

    public function test_report_excludes_manually_blocked_and_maintenance_rooms_from_available_nights(): void
    {
        $this->room('501');
        $this->room('502', RoomOperationalStatus::Maintenance);
        $this->room('503', RoomOperationalStatus::Blocked);

        $report = app(HotelAnalyticsService::class)->report(
            Carbon::parse('2026-09-10', config('app.timezone')),
            Carbon::parse('2026-09-10', config('app.timezone')),
        );

        $this->assertSame(1, $report['availableRoomNights']);
    }

    private function room(string $number, RoomOperationalStatus $status = RoomOperationalStatus::Available): Room
    {
        $floor = Floor::firstOrCreate(['name' => 'Floor '.substr($number, 0, 1)]);
        $category = RoomCategory::firstOrCreate(['name' => 'Standard']);
        $type = RoomType::firstOrCreate(['name' => 'Standard Room'], ['capacity' => 2, 'base_rate' => 100]);

        return Room::create([
            'room_number' => $number,
            'floor_id' => $floor->id,
            'room_category_id' => $category->id,
            'room_type_id' => $type->id,
            'operational_status' => $status,
            'housekeeping_status' => HousekeepingStatus::Clean,
            'base_rate' => 100,
            'capacity' => 2,
            'is_active' => true,
        ]);
    }
}
