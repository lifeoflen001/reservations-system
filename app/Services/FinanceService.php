<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\FundTransfer;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PosRefund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FinanceService
{
    public function balance(FinancialAccount|int $account): float
    {
        $id = $account instanceof FinancialAccount ? $account->getKey() : $account;
        $row = FinancialTransaction::query()->where('account_id', $id)->where('status', 'posted')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as balance")->first();
        return round((float) ($row->balance ?? 0), 2);
    }

    public function balances(): Builder
    {
        return FinancialAccount::query()->where('is_active', true)->withSum(['transactions as credits' => fn (Builder $q) => $q->where('status', 'posted')->where('direction', 'credit')], 'amount')->withSum(['transactions as debits' => fn (Builder $q) => $q->where('status', 'posted')->where('direction', 'debit')], 'amount');
    }

    public function postPayment(Payment $payment, ?int $actorId = null): ?FinancialTransaction
    {
        if ($payment->status?->value !== 'paid') return null;
        $account = $this->accountForMethod((string) $payment->method);
        if (! $account) return null;
        return $this->postOnce($account, 'guest_payment', 'credit', (float) $payment->amount, $payment->reference ?: $payment->invoice_number, 'Guest payment '.$payment->invoice_number, $payment->transaction_date, Payment::class, $payment->getKey(), $actorId ?: $payment->created_by, ['payment_id' => $payment->getKey(), 'reservation_id' => $payment->reservation_id]);
    }

    public function postPosOrder(PosOrder $order, ?int $actorId = null): void
    {
        if ($order->status !== 'completed') return;
        foreach ($order->payments as $payment) {
            if ($payment->status !== 'paid' || $payment->method === 'charge_to_room') continue;
            $account = $this->accountForMethod($payment->method);
            if (! $account) continue;
            $this->postOnce($account, 'pos_sale', 'credit', (float) $payment->amount, $payment->reference ?: $order->order_number, 'POS sale '.$order->order_number, $payment->paid_at ?: $order->completed_at, PosPayment::class, $payment->getKey(), $actorId ?: $order->cashier_id, ['order_id' => $order->getKey(), 'outlet_id' => $order->outlet_id]);
        }
    }

    public function postPosRefund(PosOrder $order, PosRefund $refund, ?int $actorId = null): ?FinancialTransaction
    {
        $payment = $order->payments->first(fn (PosPayment $payment) => $payment->method !== 'charge_to_room' && $payment->status === 'paid');
        if (! $payment) return null;
        $account = $this->accountForMethod($payment->method);
        if (! $account) return null;
        return $this->postOnce($account, 'pos_refund', 'debit', (float) $refund->amount, $payment->reference ?: $order->order_number, 'POS refund '.$order->order_number, $refund->refunded_at, PosRefund::class, $refund->getKey(), $actorId, ['order_id' => $order->getKey(), 'refund_id' => $refund->getKey()]);
    }

    public function reverseSource(string $sourceType, int $sourceId, ?int $actorId = null, string $reason = 'Reversed'): void
    {
        FinancialTransaction::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->where('status', 'posted')->get()->each(function (FinancialTransaction $transaction) use ($actorId, $reason): void {
            if ($transaction->reversed_at) return;
            $reversal = $this->postOnce(FinancialAccount::findOrFail($transaction->account_id), 'reversal', $transaction->direction === 'credit' ? 'debit' : 'credit', (float) $transaction->amount, $transaction->reference, $reason, now(), FinancialTransaction::class, $transaction->getKey(), $actorId, ['reverses' => $transaction->transaction_number]);
            $transaction->update(['status' => 'reversed', 'reversed_at' => now(), 'reversal_transaction_id' => $reversal->getKey()]);
        });
    }

    public function createExpense(array $data, int $actorId): Expense
    {
        return DB::transaction(function () use ($data, $actorId): Expense {
            $account = FinancialAccount::query()->whereKey($data['account_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
            $amount = $this->amount($data['amount'] ?? 0);
            $expense = Expense::create(['expense_number' => $this->nextNumber('expense', 'EXP-'), 'category_id' => $data['category_id'] ?? null, 'account_id' => $account->getKey(), 'department_id' => $data['department_id'] ?? null, 'amount' => $amount, 'currency' => $account->currency, 'payment_method' => $data['payment_method'] ?? null, 'payee' => $data['payee'] ?? null, 'reference' => $data['reference'] ?? null, 'description' => $data['description'], 'attachment_path' => $data['attachment_path'] ?? null, 'expense_date' => $data['expense_date'] ?? now(), 'status' => 'posted', 'created_by' => $actorId, 'paid_at' => $data['expense_date'] ?? now()]);
            $ledger = $this->postOnce($account, 'expense', 'debit', $amount, $expense->reference ?: $expense->expense_number, $expense->description, $expense->expense_date, Expense::class, $expense->getKey(), $actorId, ['expense_id' => $expense->getKey()]);
            $expense->update(['ledger_transaction_id' => $ledger->getKey()]);
            return $expense->fresh(['account', 'category', 'department', 'creator']);
        });
    }

    public function createTransfer(array $data, int $actorId): FundTransfer
    {
        return DB::transaction(function () use ($data, $actorId): FundTransfer {
            $amount = $this->amount($data['amount'] ?? 0);
            $from = FinancialAccount::query()->whereKey($data['from_account_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
            $to = FinancialAccount::query()->whereKey($data['to_account_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
            if ($from->is($to)) throw new InvalidArgumentException('Source and destination accounts must be different.');
            if ($this->balance($from) < $amount) throw new InvalidArgumentException('The source account does not have enough available funds.');
            $transfer = FundTransfer::create(['transfer_number' => $this->nextNumber('transfer', 'TRF-'), 'from_account_id' => $from->getKey(), 'to_account_id' => $to->getKey(), 'amount' => $amount, 'currency' => $from->currency, 'reference' => $data['reference'] ?? null, 'description' => $data['description'] ?? null, 'attachment_path' => $data['attachment_path'] ?? null, 'transfer_date' => $data['transfer_date'] ?? now(), 'status' => 'posted', 'created_by' => $actorId]);
            $debit = $this->postOnce($from, 'transfer', 'debit', $amount, $transfer->reference ?: $transfer->transfer_number, $transfer->description ?: 'Internal transfer', $transfer->transfer_date, FundTransfer::class, $transfer->getKey(), $actorId, ['transfer_id' => $transfer->getKey(), 'side' => 'from']);
            $credit = $this->postOnce($to, 'transfer', 'credit', $amount, $transfer->reference ?: $transfer->transfer_number, $transfer->description ?: 'Internal transfer', $transfer->transfer_date, FundTransfer::class, $transfer->getKey(), $actorId, ['transfer_id' => $transfer->getKey(), 'side' => 'to']);
            $transfer->update(['debit_transaction_id' => $debit->getKey(), 'credit_transaction_id' => $credit->getKey()]);
            return $transfer->fresh(['fromAccount', 'toAccount', 'creator']);
        });
    }

    public function accountForMethod(string $method): ?FinancialAccount
    {
        $code = match ($method) { 'cash' => 'cash', 'card' => 'card_clearing', 'mobile_money' => 'mobile_money', 'bank_transfer' => 'bank', default => null };
        return $code ? FinancialAccount::query()->where('code', $code)->where('is_active', true)->first() : null;
    }

    public function postOpeningBalance(FinancialAccount $account, float $amount, int $actorId, mixed $date = null): FinancialTransaction
    {
        return $this->postOnce($account, 'opening_balance', 'credit', $amount, 'OPEN-'.$account->code, 'Opening balance', $date ?: now(), FinancialAccount::class, $account->getKey(), $actorId, ['account_id' => $account->getKey()]);
    }

    private function postOnce(FinancialAccount $account, string $type, string $direction, float $amount, ?string $reference, ?string $description, mixed $date, ?string $sourceType, ?int $sourceId, ?int $actorId, array $metadata = []): FinancialTransaction
    {
        if ($sourceType && $sourceId && $type !== 'transfer') {
            $existing = FinancialTransaction::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->where('transaction_type', $type)->where('direction', $direction)->where('status', 'posted')->first();
            if ($existing) return $existing;
        }
        return FinancialTransaction::create(['transaction_number' => $this->nextNumber('transaction', 'FIN-'), 'account_id' => $account->getKey(), 'transaction_type' => $type, 'direction' => $direction, 'amount' => $this->amount($amount), 'currency' => $account->currency, 'reference' => $reference, 'description' => $description, 'transaction_date' => $date ?: now(), 'source_type' => $sourceType, 'source_id' => $sourceId, 'created_by' => $actorId, 'status' => 'posted', 'metadata' => $metadata]);
    }

    private function nextNumber(string $key, string $prefix): string
    {
        $sequence = DB::table('finance_sequences')->where('key', $key)->lockForUpdate()->firstOrFail();
        DB::table('finance_sequences')->where('key', $key)->update(['next_number' => $sequence->next_number + 1, 'updated_at' => now()]);
        return $prefix.str_pad((string) $sequence->next_number, 6, '0', STR_PAD_LEFT);
    }

    private function amount(mixed $amount): float
    {
        $value = round((float) $amount, 2);
        if ($value <= 0) throw new InvalidArgumentException('Amount must be greater than zero.');
        return $value;
    }
}
