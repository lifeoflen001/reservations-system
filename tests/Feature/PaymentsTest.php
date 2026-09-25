<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Events\PaymentReceived;
use App\Models\Client;
use App\Models\Floor;
use App\Models\PaymentMethod;
use App\Models\FinancialTransaction;
use App\Models\FinancialAccount;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\CurrencyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_creates_invoice_and_reduces_balance(): void
    {
        [$reservation, $user] = $this->reservation();
        $payment = app(PaymentService::class)->post($reservation, ['amount' => 100, 'method' => 'cash', 'transaction_date' => now()], $user->id);

        $this->assertSame('INV-00001', $payment->invoice_number);
        $this->assertSame('INV-00001', $payment->invoice->invoice_number);
        $this->assertSame(200.0, $reservation->fresh()->balance());
        $this->assertDatabaseHas('invoices', ['payment_id' => $payment->id, 'invoice_number' => 'INV-00001']);
    }

    public function test_overpayment_is_rejected_and_void_restores_balance(): void
    {
        [$reservation, $user] = $this->reservation();
        $payment = app(PaymentService::class)->post($reservation, ['amount' => 100, 'method' => 'cash', 'transaction_date' => now()], $user->id);

        try {
            app(PaymentService::class)->post($reservation, ['amount' => 250, 'method' => 'card', 'transaction_date' => now()], $user->id);
            $this->fail('An overpayment should be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('outstanding', strtolower($exception->getMessage()));
        }
        app(PaymentService::class)->void($payment, $user, 'Correction requested by front desk');

        $this->assertSame(PaymentStatus::Voided, $payment->fresh()->status);
        $this->assertSame(300.0, $reservation->fresh()->balance());
    }

    public function test_pending_payment_can_be_settled_without_creating_a_second_invoice(): void
    {
        [$reservation, $user] = $this->reservation();
        $payment = app(PaymentService::class)->post($reservation, ['amount' => 100, 'method' => 'cash', 'status' => PaymentStatus::Pending->value, 'transaction_date' => now()], $user->id);
        Event::fake([PaymentReceived::class]);
        $updated = app(PaymentService::class)->update($payment, ['status' => PaymentStatus::Paid->value], $user);

        $this->assertSame(PaymentStatus::Paid, $updated->status);
        $this->assertSame($payment->invoice_number, $updated->invoice->invoice_number);
        $this->assertSame(200.0, $reservation->fresh()->balance());
        Event::assertDispatchedTimes(PaymentReceived::class, 1);
    }

    public function test_partial_and_full_refunds_reduce_folio_and_post_money_out_once(): void
    {
        [$reservation, $user] = $this->reservation();
        $payment = app(PaymentService::class)->post($reservation, ['amount' => 100, 'method' => 'cash', 'transaction_date' => now()], $user->id);

        $refund = app(PaymentService::class)->refund($payment, ['amount' => 20, 'method' => 'cash', 'reason' => 'Guest correction'], $user);
        $this->assertSame('20.00', (string) $refund->amount);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(80.0, $reservation->fresh()->paidAmount());

        app(PaymentService::class)->refund($payment, ['amount' => 80, 'method' => 'cash', 'reason' => 'Guest cancellation'], $user);
        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(0.0, $reservation->fresh()->paidAmount());
        $this->assertSame(2, FinancialTransaction::where('source_type', \App\Models\PaymentRefund::class)->count());
        $this->assertSame(300.0, $reservation->fresh()->balance());
        $invoiceHtml = view('invoices.partials.document', ['invoice' => $payment->invoice->load(['payment.refunds', 'payment.reservation.room.roomType', 'payment.client']), 'formatter' => app(CurrencyFormatter::class), 'property' => null])->render();
        $this->assertStringContainsString('Payment refund', $invoiceHtml);
        $this->assertStringContainsString($refund->refund_reference, $invoiceHtml);

        $this->expectException(InvalidArgumentException::class);
        app(PaymentService::class)->refund($payment, ['amount' => 1, 'method' => 'cash', 'reason' => 'Over refund'], $user);
    }

    private function reservation(): array
    {
        FinancialAccount::create(['name' => 'Cash', 'code' => 'cash', 'type' => 'cash', 'currency' => 'USD', 'is_active' => true]);
        FinancialAccount::create(['name' => 'Card Clearing', 'code' => 'card_clearing', 'type' => 'card_clearing', 'currency' => 'USD', 'is_active' => true]);
        PaymentMethod::create(['code' => 'cash', 'name' => 'Cash', 'is_active' => true, 'sort_order' => 1]);
        PaymentMethod::create(['code' => 'card', 'name' => 'Card', 'is_active' => true, 'sort_order' => 2]);
        $user = User::create(['name' => 'Finance User', 'username' => 'finance', 'email' => 'finance@example.test', 'password' => Hash::make('secret')]);
        $client = Client::create(['first_name' => 'Test', 'last_name' => 'Guest', 'email' => 'guest@example.test']);
        $floor = Floor::create(['name' => 'Floor 1']);
        $category = RoomCategory::create(['name' => 'Standard']);
        $type = RoomType::create(['name' => 'Standard Room', 'capacity' => 2, 'base_rate' => 100]);
        $room = Room::create(['room_number' => '101', 'floor_id' => $floor->id, 'room_category_id' => $category->id, 'room_type_id' => $type->id, 'operational_status' => 'available', 'housekeeping_status' => 'clean', 'base_rate' => 100, 'capacity' => 2]);
        $reservation = Reservation::create(['code' => 'WSX-0001', 'client_id' => $client->id, 'room_id' => $room->id, 'check_in' => '2026-10-01 14:00', 'check_out' => '2026-10-04 11:00', 'adults' => 1, 'children' => 0, 'nightly_rate' => 100, 'total_amount' => 300, 'status' => 'confirmed']);
        return [$reservation, $user];
    }
}
