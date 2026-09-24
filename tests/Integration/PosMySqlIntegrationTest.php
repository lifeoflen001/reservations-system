<?php

namespace Tests\Integration;

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
use App\Services\HotelAnalyticsService;
use App\Services\PaymentService;
use App\Services\PosOrderService;
use App\Services\PosReportService;
use App\Services\PosShiftService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class PosMySqlIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private PosOutlet $outlet;
    private PosCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->outlet = PosOutlet::create(['name' => 'MySQL Audit Outlet', 'code' => 'mysql-audit-outlet', 'is_active' => true]);
        $this->category = PosCategory::create(['name' => 'MySQL Audit Category', 'code' => 'mysql-audit-category', 'is_active' => true]);
    }

    public function test_mysql_schema_constraints_decimal_math_and_idempotent_stock_checkout(): void
    {
        foreach (['pos_orders', 'pos_order_items', 'pos_payments', 'pos_room_charges', 'pos_shifts'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' is missing after incremental migrations.');
        }

        $this->expectException(QueryException::class);
        PosOutlet::create(['name' => 'Duplicate outlet', 'code' => $this->outlet->code, 'is_active' => true]);
    }

    public function test_mysql_foreign_keys_tax_currency_math_and_duplicate_checkout_protection(): void
    {
        try {
            PosProduct::create(['category_id' => 999999999, 'outlet_id' => $this->outlet->id, 'name' => 'Invalid FK', 'sku' => 'MYSQL-FK-FAIL', 'selling_price' => 1, 'is_active' => true]);
            $this->fail('MySQL accepted an invalid POS category foreign key.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $product = $this->product('Decimal item', 'MYSQL-DECIMAL', 33.33, 5, 10);
        $order = app(PosOrderService::class)->checkout([
            'outlet_id' => $this->outlet->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 73.33]],
            'idempotency_key' => 'mysql-audit-decimal-1',
        ], $this->admin);

        $this->assertSame(66.66, (float) $order->subtotal);
        $this->assertSame(6.67, (float) $order->tax_total);
        $this->assertSame(73.33, (float) $order->total);
        $this->assertSame(3.0, (float) $product->fresh()->stock_quantity);

        $duplicate = app(PosOrderService::class)->checkout([
            'outlet_id' => $this->outlet->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 73.33]],
            'idempotency_key' => 'mysql-audit-decimal-1',
        ], $this->admin);
        $this->assertSame($order->id, $duplicate->id);
        $this->assertSame(1, PosOrder::where('idempotency_key', 'mysql-audit-decimal-1')->count());

        try {
            app(PosOrderService::class)->checkout([
                'outlet_id' => $this->outlet->id,
                'items' => [['product_id' => $product->id, 'quantity' => 4]],
                'payments' => [['method' => 'cash', 'amount' => 146.66]],
            ], $this->admin);
            $this->fail('Checkout accepted more stock than was available.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Insufficient stock', $exception->getMessage());
        }
    }

    public function test_mysql_room_charge_payment_reconciliation_and_all_surfaces(): void
    {
        [$reservation, $client] = $this->inHouseReservation(100);
        $product = $this->product('Audit minibar item', 'MYSQL-ROOM-5', 5, 5);
        $order = app(PosOrderService::class)->checkout([
            'outlet_id' => $this->outlet->id,
            'reservation_id' => $reservation->id,
            'client_id' => $client->id,
            'room_id' => $reservation->room_id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'charge_to_room', 'amount' => 5]],
            'idempotency_key' => 'mysql-audit-room-charge-1',
        ], $this->admin);

        $this->assertSame(105.0, app(FinancialService::class)->totalDue($reservation));
        $this->assertSame(105.0, app(FinancialService::class)->balance($reservation));

        $firstPayment = app(PaymentService::class)->post($reservation, ['amount' => 20, 'method' => 'cash'], $this->admin->id);
        $this->assertSame(85.0, app(FinancialService::class)->balance($reservation));
        $accommodationPayment = app(PaymentService::class)->post($reservation, ['amount' => 80, 'method' => 'cash'], $this->admin->id);
        $posSettlement = app(PaymentService::class)->post($reservation, ['amount' => 5, 'method' => 'cash'], $this->admin->id);
        $reservation->refresh();

        $this->assertSame(105.0, app(FinancialService::class)->totalDue($reservation));
        $this->assertSame(0.0, app(FinancialService::class)->balance($reservation));
        $this->assertSame(105.0, (float) app(FinancialService::class)->paidAmount($reservation));
        $this->assertSame(5.0, (float) app(PosReportService::class)->summary(now()->startOfDay(), now()->endOfDay())['sales']);
        $this->assertSame(5.0, (float) app(PosReportService::class)->summary(now()->startOfDay(), now()->endOfDay())['room_charges']);

        $from = now()->startOfDay();
        $to = now()->endOfDay();
        $this->assertSame(105.0, (float) app(HotelAnalyticsService::class)->report($from, $to)['revenue']);
        $dashboard = app(HotelAnalyticsService::class)->dashboard('custom', $from, $to);
        $this->assertSame('$105.00', collect($dashboard['metrics'])->firstWhere('label', 'Revenue today')['value']);

        $formatter = app(\App\Support\CurrencyFormatter::class);
        $reservationPage = $this->actingAs($this->admin)->get(route('reservations.index', ['reservation' => $reservation->id]));
        $reservationPage->assertOk()->assertSee($reservation->code)->assertSee($formatter->format(105))->assertSee($order->order_number);
        $invoicePage = $this->actingAs($this->admin)->get(route('invoices.show', $posSettlement->invoice));
        $invoicePage->assertOk()->assertSee($order->order_number)->assertSee($this->outlet->name)->assertSee('Audit minibar item')->assertSee($order->roomCharge->posted_at->format('m/d/Y'))->assertSee('POS room charge');
        $this->actingAs($this->admin)->get(route('payments.index', ['search' => $reservation->code]))->assertOk()->assertSee($firstPayment->invoice_number)->assertSee($accommodationPayment->invoice_number)->assertSee($posSettlement->invoice_number);
        $this->actingAs($this->admin)->get(route('pos.orders.show', $order))->assertOk()->assertSee($order->order_number)->assertSee($this->outlet->name)->assertSee('5.00');
        $this->actingAs($this->admin)->get(route('pos.reports', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))->assertOk()->assertSee('5.00');
        $this->actingAs($this->admin)->get(route('reports.index', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))->assertOk()->assertSee($formatter->format(105));
    }

    public function test_mysql_void_refund_and_shift_constraints(): void
    {
        $product = $this->product('Audit stock item', 'MYSQL-STOCK', 2, 20);
        $orders = app(PosOrderService::class);
        $voided = $orders->checkout($this->saleData($product, 2, 'mysql-audit-void'), $this->admin);
        $this->assertSame(19.0, (float) $product->fresh()->stock_quantity);
        $orders->void($voided, $this->admin, 'integration void');
        $this->assertSame('voided', $voided->fresh()->status);
        $this->assertSame(20.0, (float) $product->fresh()->stock_quantity);

        $refunded = $orders->checkout($this->saleData($product, 2, 'mysql-audit-refund'), $this->admin);
        $orders->refund($refunded, $this->admin, 2, 'integration refund');
        $this->assertSame('refunded', $refunded->fresh()->status);
        $this->assertSame(20.0, (float) $product->fresh()->stock_quantity);

        $shifts = app(PosShiftService::class);
        $shift = $shifts->open($this->outlet, $this->admin, 10);
        try {
            $shifts->open($this->outlet, $this->admin, 10);
            $this->fail('A cashier was allowed to open two shifts for one outlet.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('open shift', $exception->getMessage());
        }
        $shifted = $orders->checkout($this->saleData($product, 2, 'mysql-audit-shift') + ['shift_id' => $shift->id], $this->admin);
        $closed = $shifts->close($shift, $this->admin, 12);
        $this->assertSame('closed', $closed->status);
        $this->assertSame(2.0, (float) $closed->cash_sales);
        $this->assertSame(12.0, (float) $closed->expected_cash);
        $this->assertSame($shift->id, $shifted->shift_id);
    }

    private function product(string $name, string $sku, float $price, float $stock, float $tax = 0): PosProduct
    {
        return PosProduct::create([
            'category_id' => $this->category->id,
            'outlet_id' => $this->outlet->id,
            'name' => $name,
            'sku' => $sku,
            'selling_price' => $price,
            'tax_rate' => $tax,
            'is_active' => true,
            'track_stock' => true,
            'stock_quantity' => $stock,
            'reorder_level' => 1,
        ]);
    }

    private function saleData(PosProduct $product, float $amount, string $key): array
    {
        return ['outlet_id' => $this->outlet->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => $amount]], 'idempotency_key' => $key];
    }

    private function inHouseReservation(float $total): array
    {
        $client = Client::create(['first_name' => 'MySQL', 'last_name' => 'Audit Guest', 'email' => 'mysql-audit-'.uniqid().'@example.test']);
        $floor = Floor::create(['name' => 'MySQL Audit Floor']);
        $category = RoomCategory::create(['name' => 'MySQL Audit Category']);
        $type = RoomType::create(['name' => 'MySQL Audit Room', 'capacity' => 2, 'base_rate' => $total]);
        $room = Room::create(['room_number' => 'M'.random_int(100, 999), 'floor_id' => $floor->id, 'room_category_id' => $category->id, 'room_type_id' => $type->id, 'operational_status' => RoomOperationalStatus::Occupied, 'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => $total, 'capacity' => 2]);
        $reservation = Reservation::create(['code' => 'MYSQL-'.random_int(10000, 99999), 'client_id' => $client->id, 'room_id' => $room->id, 'created_by' => $this->admin->id, 'check_in' => now()->subDay(), 'check_out' => now()->addDay(), 'adults' => 1, 'children' => 0, 'nightly_rate' => $total, 'total_amount' => $total, 'status' => ReservationStatus::CheckedIn]);
        return [$reservation, $client];
    }
}
