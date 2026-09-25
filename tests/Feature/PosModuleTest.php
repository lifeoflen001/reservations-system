<?php

namespace Tests\Feature;

use App\Enums\HousekeepingStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Models\Client;
use App\Models\Floor;
use App\Models\PosCategory;
use App\Models\PosOrder;
use App\Models\PosOutlet;
use App\Models\PosProduct;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Models\User;
use App\Services\FinancialService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private PosOutlet $outlet;
    private PosProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->outlet = PosOutlet::create(['name' => 'Main Restaurant', 'code' => 'restaurant', 'location' => 'Ground floor', 'is_active' => true]);
        $category = PosCategory::create(['name' => 'Food', 'code' => 'food', 'is_active' => true]);
        $this->product = PosProduct::create(['category_id' => $category->id, 'outlet_id' => $this->outlet->id, 'name' => 'Club sandwich', 'sku' => 'FOOD-001', 'selling_price' => 15000, 'tax_rate' => 0, 'is_active' => true, 'track_stock' => true, 'stock_quantity' => 10, 'reorder_level' => 2]);
    }

    public function test_terminal_checkout_recalculates_total_deducts_stock_and_generates_receipt(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('pos.checkout'), [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 1]],
            'idempotency_key' => 'pos-test-cash-1',
        ]);

        $response->assertStatus(422)->assertJsonPath('message', 'Payment amounts must equal the sale total.');
        $this->assertDatabaseCount('pos_orders', 0);

        $response = $this->actingAs($this->admin)->postJson(route('pos.checkout'), [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 30000]],
            'idempotency_key' => 'pos-test-cash-2',
        ]);

        $response->assertOk()->assertJsonPath('order.total', 30000);
        $order = PosOrder::firstOrFail();
        $this->assertSame(8.0, (float) $this->product->fresh()->stock_quantity);
        $this->assertDatabaseHas('financial_transactions', ['source_type' => \App\Models\PosPayment::class, 'transaction_type' => 'pos_sale', 'direction' => 'credit', 'amount' => 30000]);
        $this->actingAs($this->admin)->get(route('pos.receipts.show', $order))->assertOk()->assertSee($order->order_number);
        $this->actingAs($this->admin)->get(route('pos.receipts.download', $order))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'attachment; filename="'.$order->order_number.'.pdf"');
        $this->actingAs($this->admin)->postJson(route('pos.checkout'), [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 30000]],
            'idempotency_key' => 'pos-test-cash-2',
        ])->assertOk()->assertJsonPath('order.id', $order->id);
        $this->assertDatabaseCount('pos_orders', 1);
    }

    public function test_room_charge_updates_authoritative_reservation_balance_and_void_restores_stock(): void
    {
        [$reservation, $client] = $this->inHouseReservation();
        $response = $this->actingAs($this->admin)->postJson(route('pos.checkout'), [
            'outlet_id' => $this->outlet->id,
            'reservation_id' => $reservation->id,
            'client_id' => $client->id,
            'room_id' => $reservation->room_id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payments' => [['method' => 'charge_to_room', 'amount' => 15000]],
            'idempotency_key' => 'pos-test-room-1',
        ]);

        $response->assertOk();
        $order = PosOrder::firstOrFail();
        $this->assertSame(115000.0, app(FinancialService::class)->totalDue($reservation));
        $this->assertSame(115000.0, app(FinancialService::class)->balance($reservation));
        $this->assertDatabaseHas('pos_room_charges', ['order_id' => $order->id, 'reservation_id' => $reservation->id, 'status' => 'active', 'amount' => 15000]);
        $this->assertDatabaseMissing('financial_transactions', ['source_type' => \App\Models\PosPayment::class, 'source_id' => $order->payments()->value('id')]);

        $this->actingAs($this->admin)->post(route('pos.orders.void', $order), ['reason' => 'Duplicate test sale'])->assertRedirect();
        $this->assertSame(100000.0, app(FinancialService::class)->totalDue($reservation));
        $this->assertSame(10.0, (float) $this->product->fresh()->stock_quantity);
        $this->assertDatabaseHas('pos_room_charges', ['order_id' => $order->id, 'status' => 'voided']);
    }

    public function test_pos_shift_open_and_close_records_cash_variance(): void
    {
        $this->actingAs($this->admin)->post(route('pos.shifts.open'), ['outlet_id' => $this->outlet->id, 'opening_cash' => 200000])->assertRedirect();
        $shift = $this->admin->posShifts()->latest('id')->first();
        $this->assertNotNull($shift);
        $this->actingAs($this->admin)->post(route('pos.shifts.close', $shift), ['actual_cash' => 190000])->assertRedirect();
        $this->assertSame('closed', $shift->fresh()->status);
        $this->assertSame(-10000.0, (float) $shift->fresh()->variance);
    }

    private function inHouseReservation(): array
    {
        $client = Client::create(['first_name' => 'Emily', 'last_name' => 'Johnson', 'email' => 'emily@example.test']);
        $floor = Floor::create(['name' => 'Ground floor']);
        $category = RoomCategory::create(['name' => 'Executive']);
        $type = RoomType::create(['name' => 'Executive Suite', 'capacity' => 2, 'base_rate' => 100000]);
        $room = Room::create(['room_number' => '903', 'floor_id' => $floor->id, 'room_category_id' => $category->id, 'room_type_id' => $type->id, 'operational_status' => RoomOperationalStatus::Occupied, 'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => 100000, 'capacity' => 2]);
        $reservation = Reservation::create(['code' => 'WSX-1024', 'client_id' => $client->id, 'room_id' => $room->id, 'created_by' => $this->admin->id, 'check_in' => now()->subDay(), 'check_out' => now()->addDay(), 'adults' => 1, 'children' => 0, 'nightly_rate' => 100000, 'total_amount' => 100000, 'status' => ReservationStatus::CheckedIn]);
        return [$reservation, $client];
    }
}
