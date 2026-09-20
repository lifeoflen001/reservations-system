<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Exceptions\RoomUnavailableException;
use App\Models\Client;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Services\PaymentService;
use App\Services\RoomAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlapping_active_reservations_are_rejected_but_cancelled_reservations_do_not_block(): void
    {
        $room = $this->room();
        $client = Client::create(['first_name' => 'A', 'last_name' => 'Guest']);

        Reservation::create([
            'code' => 'RES-1', 'client_id' => $client->id, 'room_id' => $room->id,
            'check_in' => '2026-10-10 14:00', 'check_out' => '2026-10-12 11:00',
            'status' => ReservationStatus::Confirmed, 'total_amount' => 200,
        ]);

        $service = app(RoomAvailabilityService::class);
        $this->assertFalse($service->isAvailable($room, now()->parse('2026-10-11'), now()->parse('2026-10-13')));

        Reservation::query()->update(['status' => ReservationStatus::Cancelled]);

        $this->assertTrue($service->isAvailable($room, now()->parse('2026-10-11'), now()->parse('2026-10-13')));
    }

    public function test_assert_available_raises_a_domain_exception_for_invalid_room_ranges(): void
    {
        $room = $this->room();
        $service = app(RoomAvailabilityService::class);

        $this->expectException(RoomUnavailableException::class);
        $service->assertAvailable($room, now()->parse('2026-10-12'), now()->parse('2026-10-10'));
    }

    public function test_paid_amount_and_balance_are_derived_from_successful_payments(): void
    {
        $room = $this->room();
        $client = Client::create(['first_name' => 'A', 'last_name' => 'Guest']);
        $reservation = Reservation::create([
            'code' => 'RES-2', 'client_id' => $client->id, 'room_id' => $room->id,
            'check_in' => '2026-10-10 14:00', 'check_out' => '2026-10-12 11:00',
            'status' => ReservationStatus::Confirmed, 'total_amount' => 555,
        ]);

        app(PaymentService::class)->post($reservation, ['amount' => 185, 'method' => 'cash']);

        $this->assertSame(185.0, $reservation->fresh()->paidAmount());
        $this->assertSame(370.0, $reservation->fresh()->balance());
    }

    private function room(): Room
    {
        $floor = Floor::create(['name' => 'Floor 1']);
        $category = RoomCategory::create(['name' => 'Standard']);
        $type = RoomType::create(['name' => 'Standard Room', 'capacity' => 2, 'base_rate' => 95]);

        return Room::create([
            'room_number' => '101', 'floor_id' => $floor->id, 'room_category_id' => $category->id,
            'room_type_id' => $type->id, 'operational_status' => RoomOperationalStatus::Available,
            'housekeeping_status' => 'clean', 'base_rate' => 95, 'capacity' => 2,
        ]);
    }
}
