<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Client;
use App\Models\PaymentMethod;
use App\Models\PosCategory;
use App\Models\PosOutlet;
use App\Models\PosProduct;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\ApiTokenService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1ExpansionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    private Client $client;

    private Room $room;

    private Reservation $reservation;

    private PosOutlet $outlet;

    private PosProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::firstOrFail();
        [, $this->token] = app(ApiTokenService::class)->issue($this->admin, 'API expansion tests', config('hotel.api_token_scopes'));
        PaymentMethod::firstOrCreate(['code' => 'cash'], ['name' => 'Cash', 'is_active' => true, 'sort_order' => 1]);
        $this->client = Client::create(['first_name' => 'API', 'last_name' => 'Guest', 'email' => 'api-guest@example.test', 'is_active' => true]);
        $this->room = Room::where('room_number', '100')->firstOrFail();
        $this->room->update(['operational_status' => 'occupied']);
        $this->reservation = Reservation::create([
            'code' => 'API-0001',
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'check_in' => now()->subDay(),
            'check_out' => now()->addDay(),
            'adults' => 1,
            'children' => 0,
            'nightly_rate' => 100,
            'total_amount' => 100,
            'status' => ReservationStatus::CheckedIn,
        ]);
        $this->outlet = PosOutlet::create(['name' => 'API Outlet', 'code' => 'api-outlet', 'is_active' => true]);
        $category = PosCategory::create(['name' => 'API Items', 'code' => 'api-items', 'is_active' => true]);
        $this->product = PosProduct::create([
            'category_id' => $category->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'API Water',
            'sku' => 'API-WATER',
            'selling_price' => 5,
            'tax_rate' => 0,
            'is_active' => true,
            'track_stock' => false,
            'stock_quantity' => 0,
            'reorder_level' => 0,
        ]);
    }

    private function api(): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)->withHeader('Accept', 'application/json');
    }

    public function test_extended_resources_are_available_with_pagination_and_documentation(): void
    {
        $this->api()->getJson(route('api.v1.pos.outlets'))->assertOk()->assertJsonPath('data.0.code', 'api-outlet');
        $this->api()->getJson(route('api.v1.pos.products', ['per_page' => 1]))->assertOk()->assertJsonPath('meta.per_page', 1)->assertJsonPath('data.0.sku', 'API-WATER');
        $this->api()->getJson(route('api.v1.finance.accounts'))->assertOk()->assertJsonPath('data.0.balance', 0);
        $this->api()->getJson(route('api.v1.staff.index'))->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
        $this->get('/api/openapi.yaml')->assertOk()->assertHeader('content-type', 'application/yaml; charset=UTF-8')->assertSee('Lodgix Hotel Management API');
    }

    public function test_api_payment_starts_pending_and_confirmation_posts_once(): void
    {
        $payload = ['reservation_id' => $this->reservation->id, 'amount' => 20, 'method' => 'cash', 'reference' => 'API-PAY-001'];
        $created = $this->api()->postJson(route('api.v1.payments.store'), $payload)->assertCreated()->assertJsonPath('data.status', 'pending');
        $paymentId = $created->json('data.id');
        $this->assertDatabaseMissing('financial_transactions', ['source_type' => 'App\\Models\\Payment', 'source_id' => $paymentId]);

        $this->api()->postJson(route('api.v1.payments.confirm', $paymentId), [])->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->api()->postJson(route('api.v1.payments.confirm', $paymentId), [])->assertOk()->assertJsonPath('meta.idempotent_replay', true);
        $this->assertDatabaseCount('financial_transactions', 1);

        $this->api()->postJson(route('api.v1.payments.store'), $payload)->assertOk()->assertJsonPath('meta.idempotent_replay', true);
    }

    public function test_pos_orders_use_existing_checkout_rules_and_idempotency(): void
    {
        $payload = [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 5, 'reference' => 'API-POS-001']],
        ];
        $first = $this->api()->withHeader('Idempotency-Key', 'api-pos-001')->postJson(route('api.v1.pos.orders.store'), $payload)->assertCreated();
        $second = $this->api()->withHeader('Idempotency-Key', 'api-pos-001')->postJson(route('api.v1.pos.orders.store'), $payload)->assertOk()->assertJsonPath('meta.idempotent_replay', true);
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->api()->getJson(route('api.v1.pos.orders.show', $first->json('data.id')))->assertOk()->assertJsonPath('data.total', 5);
        $this->api()->getJson(route('api.v1.pos.reports'))->assertOk()->assertJsonPath('data.orders', 1);
    }

    public function test_reservation_and_invoice_api_expose_the_same_folio_figures(): void
    {
        $roomCharge = [
            'outlet_id' => $this->outlet->id,
            'reservation_id' => $this->reservation->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payments' => [['method' => 'charge_to_room', 'amount' => 5]],
        ];
        $this->api()->withHeader('Idempotency-Key', 'api-room-charge-001')->postJson(route('api.v1.pos.orders.store'), $roomCharge)->assertCreated();
        $payment = $this->api()->postJson(route('api.v1.payments.store'), ['reservation_id' => $this->reservation->id, 'amount' => 20, 'method' => 'cash', 'reference' => 'API-FOLIO-001'])->assertCreated();
        $paymentId = $payment->json('data.id');
        $this->api()->postJson(route('api.v1.payments.confirm', $paymentId), [])->assertOk();

        $reservation = $this->api()->getJson(route('api.v1.reservations.show', $this->reservation))->assertOk();
        $this->assertSame(105.0, (float) $reservation->json('data.total_due'));
        $this->assertSame(20.0, (float) $reservation->json('data.paid'));
        $this->assertSame(85.0, (float) $reservation->json('data.balance'));

        $invoiceId = \App\Models\Payment::findOrFail($paymentId)->invoice->id;
        $invoice = $this->api()->getJson(route('api.v1.invoices.show', $invoiceId))->assertOk();
        $this->assertSame(2, count($invoice->json('data.line_items')));
        $this->assertSame('API Outlet', $invoice->json('data.line_items.1.outlet'));
        $this->assertSame(5.0, (float) $invoice->json('data.line_items.1.amount'));
    }
}
