<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Client;
use App\Models\Floor;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\FundTransfer;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Enums\HousekeepingStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Services\FinanceService;
use App\Services\PaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_is_authoritative_for_payments_expenses_and_transfers(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = \App\Models\User::where('username', 'admin')->firstOrFail();
        $client = Client::create(['first_name' => 'Finance', 'last_name' => 'Guest']);
        $floor = Floor::create(['name' => 'Finance Floor']);
        $category = RoomCategory::create(['name' => 'Finance Category']);
        $type = RoomType::create(['name' => 'Finance Room', 'capacity' => 2, 'base_rate' => 100]);
        $room = Room::create(['room_number' => 'F-101', 'floor_id' => $floor->id, 'room_category_id' => $category->id, 'room_type_id' => $type->id, 'operational_status' => RoomOperationalStatus::Available, 'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => 100, 'capacity' => 2]);
        $reservation = Reservation::create(['code' => 'FIN-0001', 'client_id' => $client->id, 'room_id' => $room->id, 'check_in' => now(), 'check_out' => now()->addDay(), 'total_amount' => 100, 'status' => ReservationStatus::Confirmed]);
        $payment = app(PaymentService::class)->post($reservation, ['amount' => 100, 'method' => 'cash', 'transaction_date' => now()], $admin->id);
        $finance = app(FinanceService::class);

        $finance->postPayment($payment, $admin->id);
        $finance->postPayment($payment, $admin->id);
        $expense = $finance->createExpense(['account_id' => FinancialAccount::where('code', 'cash')->value('id'), 'amount' => 25.50, 'expense_date' => now(), 'description' => 'Petty supplies'], $admin->id);
        $transfer = $finance->createTransfer(['from_account_id' => FinancialAccount::where('code', 'cash')->value('id'), 'to_account_id' => FinancialAccount::where('code', 'bank')->value('id'), 'amount' => 10, 'transfer_date' => now(), 'description' => 'Daily cash deposit'], $admin->id);

        $this->assertInstanceOf(Expense::class, $expense);
        $this->assertInstanceOf(FundTransfer::class, $transfer);
        $this->assertSame(1, FinancialTransaction::where('source_type', Payment::class)->where('source_id', $payment->id)->count());
        $this->assertSame(1, FinancialTransaction::where('source_type', Expense::class)->where('source_id', $expense->id)->count());
        $this->assertSame(2, FinancialTransaction::where('source_type', FundTransfer::class)->where('source_id', $transfer->id)->count());
        $this->assertSame(64.50, $finance->balance(FinancialAccount::where('code', 'cash')->firstOrFail()));
        $this->assertSame(10.0, $finance->balance(FinancialAccount::where('code', 'bank')->firstOrFail()));
    }

    public function test_finance_pages_are_rbac_protected_and_render_for_administrator(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = \App\Models\User::where('username', 'admin')->firstOrFail();
        $this->actingAs($admin)->get(route('finance.overview'))->assertOk()->assertSee('Available funds');
        $this->actingAs($admin)->get(route('finance.transactions'))->assertOk()->assertSee('Finance ledger');
        $this->actingAs($admin)->get(route('finance.expenses'))->assertOk()->assertSee('Expenses');
        $this->actingAs($admin)->get(route('finance.accounts'))->assertOk()->assertSee('Financial accounts');
        $this->actingAs($admin)->get(route('finance.transfers'))->assertOk()->assertSee('Account transfers');
        $this->actingAs($admin)->get(route('finance.petty-cash'))->assertOk()->assertSee('Petty cash');
        $this->actingAs($admin)->get(route('finance.reconciliation'))->assertOk()->assertSee('Bank reconciliation');
    }
}
