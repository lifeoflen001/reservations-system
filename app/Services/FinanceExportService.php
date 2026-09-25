<?php

namespace App\Services;

use App\Models\DailyCashClose;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\FinanceReconciliation;
use App\Models\FundTransfer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

class FinanceExportService
{
    public const REPORTS = [
        'transactions', 'expenses', 'accounts', 'account-statements',
        'transfers', 'cash-flow', 'petty-cash', 'reconciliation', 'daily-cash', 'monthly-summary',
    ];

    public function audit(Request $request, string $report, string $format): void
    {
        Log::info('finance.export', [
            'user_id' => $request->user()?->getKey(),
            'report' => $report,
            'format' => $format,
            'filters' => $request->only(['from', 'to', 'account_id', 'type', 'direction', 'status', 'category_id', 'department_id', 'payment_method']),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function rows(Request $request, string $report): LazyCollection
    {
        return match ($report) {
            'transactions', 'cash-flow', 'monthly-summary' => $this->transactionRows($request, $report),
            'expenses' => $this->expenseRows($request),
            'accounts' => $this->accountRows($request),
            'account-statements' => $this->accountStatementRows($request),
            'transfers' => $this->transferRows($request),
            'petty-cash' => $this->pettyCashRows($request),
            'reconciliation' => $this->reconciliationRows($request),
            'daily-cash' => $this->dailyCashRows($request),
            default => LazyCollection::make(),
        };
    }

    public function headers(string $report): array
    {
        return match ($report) {
            'transactions', 'cash-flow', 'monthly-summary' => ['Transaction Number', 'Date', 'Type', 'Description', 'Account', 'Money In', 'Money Out', 'Currency', 'Reference', 'Status', 'Created By'],
            'expenses' => ['Expense Number', 'Date', 'Category', 'Department', 'Payee', 'Account', 'Amount', 'Currency', 'Status', 'Reference', 'Created By', 'Approved By'],
            'accounts' => ['Account', 'Code', 'Account Type', 'Currency', 'Balance', 'Status'],
            'account-statements' => ['Property', 'Account', 'Account Type', 'Period', 'Opening Balance', 'Date', 'Transaction', 'Description', 'Debit / Money Out', 'Credit / Money In', 'Running Balance', 'Closing Balance'],
            'transfers' => ['Transfer Number', 'Date', 'From Account', 'To Account', 'Amount', 'Currency', 'Reference', 'Status', 'Created By', 'Approved By'],
            'petty-cash' => ['Date', 'Transaction', 'Description', 'Opening Balance', 'Funds Added', 'Expenses', 'Returned Funds', 'Closing Balance', 'Variance', 'Reference'],
            'reconciliation' => ['Account', 'Period', 'Opening Statement Balance', 'Closing Statement Balance', 'System Balance', 'Difference', 'Status', 'Reconciled By', 'Reconciled At'],
            'daily-cash' => ['Account', 'Date', 'Opening Balance', 'Funds Added', 'Expenses', 'Expected Closing Balance', 'Actual Counted Cash', 'Variance', 'Closed By', 'Closed At'],
            default => [],
        };
    }

    public function filename(Request $request, string $report, string $format): string
    {
        $suffix = $request->filled('from') && $request->filled('to')
            ? '-'.str_replace('-', '', (string) $request->input('from')).'-to-'.str_replace('-', '', (string) $request->input('to'))
            : '-'.now()->format('Y-m');

        return 'finance-'.str_replace('_', '-', $report).$suffix.'.'.$format;
    }

    private function transactionRows(Request $request, string $report): LazyCollection
    {
        $query = FinancialTransaction::query()->with(['account', 'creator'])
            ->whereIn('status', ['posted', 'reversed'])
            ->when($request->filled('search'), function (Builder $q) use ($request): void {
                $term = '%'.trim((string) $request->input('search')).'%';
                $q->where(fn (Builder $nested) => $nested->where('transaction_number', 'like', $term)->orWhere('reference', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when($request->filled('account_id'), fn (Builder $q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('type') && $request->input('type') !== 'all', fn (Builder $q) => $q->where('transaction_type', $request->input('type')))
            ->when($request->filled('status') && $request->input('status') !== 'all', fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->filled('direction') && $request->input('direction') !== 'all', fn (Builder $q) => $q->where('direction', $request->input('direction')))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('transaction_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('transaction_date', '<=', $request->date('to')))
            ->latest('transaction_date')->latest('id');

        return $query->lazyById(500)->map(function (FinancialTransaction $transaction): array {
            return [$transaction->transaction_number, $transaction->transaction_date?->toDateTimeString(), $transaction->transaction_type, $transaction->description, $transaction->account?->name, $transaction->direction === 'credit' ? (float) $transaction->amount : 0, $transaction->direction === 'debit' ? (float) $transaction->amount : 0, $transaction->currency, $transaction->reference, $transaction->status, $transaction->creator?->display_name ?? 'System'];
        });
    }

    private function expenseRows(Request $request): LazyCollection
    {
        return Expense::query()->with(['account', 'category', 'department', 'creator', 'approver'])
            ->when($request->filled('account_id'), fn (Builder $q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('category_id'), fn (Builder $q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('department_id'), fn (Builder $q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('status') && $request->input('status') !== 'all', fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('expense_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('expense_date', '<=', $request->date('to')))
            ->latest('expense_date')->latest('id')->lazyById(500)
            ->map(fn (Expense $expense): array => [$expense->expense_number, $expense->expense_date?->toDateTimeString(), $expense->category?->name ?? 'Uncategorised', $expense->department?->name ?? 'Unassigned', $expense->payee, $expense->account?->name, (float) $expense->amount, $expense->currency, $expense->status, $expense->reference, $expense->creator?->display_name ?? 'System', $expense->approver?->display_name]);
    }

    private function accountRows(Request $request): LazyCollection
    {
        return FinancialAccount::query()->where('is_active', true)
            ->when($request->filled('account_id'), fn (Builder $q) => $q->whereKey($request->integer('account_id')))
            ->withSum(['transactions as credits' => fn ($q) => $q->whereIn('status', ['posted', 'reversed'])->where('direction', 'credit')], 'amount')
            ->withSum(['transactions as debits' => fn ($q) => $q->whereIn('status', ['posted', 'reversed'])->where('direction', 'debit')], 'amount')
            ->orderBy('name')->lazyById(250)
            ->map(fn (FinancialAccount $account): array => [$account->name, $account->code, str_replace('_', ' ', ucfirst($account->type)), $account->currency, round((float) $account->credits - (float) $account->debits, 2), $account->is_active ? 'active' : 'inactive']);
    }

    private function accountStatementRows(Request $request): LazyCollection
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();
        $accounts = FinancialAccount::query()->where('is_active', true)->when($request->filled('account_id'), fn (Builder $q) => $q->whereKey($request->integer('account_id')))->orderBy('name')->get();

        return LazyCollection::make(function () use ($accounts, $from, $to): iterable {
            foreach ($accounts as $account) {
                $opening = (float) $account->transactions()->whereIn('status', ['posted', 'reversed'])->where('transaction_date', '<', $from)->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as balance")->value('balance');
                $running = $opening;
                foreach ($account->transactions()->whereIn('status', ['posted', 'reversed'])->whereBetween('transaction_date', [$from, $to])->orderBy('transaction_date')->orderBy('id')->cursor() as $transaction) {
                    $running = round($running + ($transaction->direction === 'credit' ? (float) $transaction->amount : -(float) $transaction->amount), 2);
                    yield [config('hotel.defaults.property_name'), $account->name, str_replace('_', ' ', ucfirst($account->type)), $from->toDateString().' to '.$to->toDateString(), $opening, $transaction->transaction_date?->toDateTimeString(), $transaction->transaction_number, $transaction->description, $transaction->direction === 'debit' ? (float) $transaction->amount : 0, $transaction->direction === 'credit' ? (float) $transaction->amount : 0, $running, null];
                }
                yield [config('hotel.defaults.property_name'), $account->name, str_replace('_', ' ', ucfirst($account->type)), $from->toDateString().' to '.$to->toDateString(), $opening, null, null, 'Closing balance', null, null, $running, $running];
            }
        });
    }

    private function transferRows(Request $request): LazyCollection
    {
        return FundTransfer::query()->with(['fromAccount', 'toAccount', 'creator', 'debitTransaction.approver'])
            ->when($request->filled('status') && $request->input('status') !== 'all', fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('transfer_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('transfer_date', '<=', $request->date('to')))
            ->latest('transfer_date')->latest('id')->lazyById(500)
            ->map(fn (FundTransfer $transfer): array => [$transfer->transfer_number, $transfer->transfer_date?->toDateTimeString(), $transfer->fromAccount?->name, $transfer->toAccount?->name, (float) $transfer->amount, $transfer->currency, $transfer->reference, $transfer->status, $transfer->creator?->display_name ?? 'System', $transfer->debitTransaction?->approver?->display_name]);
    }

    private function pettyCashRows(Request $request): LazyCollection
    {
        $account = FinancialAccount::query()->where('code', 'petty_cash')->first();
        if (! $account) return LazyCollection::make();
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();
        $opening = (float) $account->transactions()->whereIn('status', ['posted', 'reversed'])->where('transaction_date', '<', $from)->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as balance")->value('balance');
        return LazyCollection::make(function () use ($account, $from, $to, $opening): iterable {
            $running = $opening;
            foreach ($account->transactions()->whereIn('status', ['posted', 'reversed'])->whereBetween('transaction_date', [$from, $to])->orderBy('transaction_date')->orderBy('id')->cursor() as $transaction) {
                $before = $running;
                $movement = $transaction->direction === 'credit' ? (float) $transaction->amount : -(float) $transaction->amount;
                $running = round($running + $movement, 2);
                yield [$transaction->transaction_date?->toDateTimeString(), $transaction->transaction_number, $transaction->description, $before, $transaction->direction === 'credit' ? (float) $transaction->amount : 0, $transaction->direction === 'debit' ? (float) $transaction->amount : 0, 0, $running, 0, $transaction->reference];
            }
        });
    }

    private function reconciliationRows(Request $request): LazyCollection
    {
        return FinanceReconciliation::query()->with(['account', 'completer'])
            ->when($request->filled('account_id'), fn (Builder $q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('status') && $request->input('status') !== 'all', fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('period_start', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('period_end', '<=', $request->date('to')))
            ->latest('period_end')->lazyById(250)
            ->map(fn (FinanceReconciliation $item): array => [$item->account?->name, $item->period_start?->toDateString().' to '.$item->period_end?->toDateString(), (float) $item->opening_balance, (float) $item->statement_balance, (float) $item->system_balance, (float) $item->difference, $item->status, $item->completer?->display_name ?? 'Pending', $item->completed_at?->toDateTimeString()]);
    }

    private function dailyCashRows(Request $request): LazyCollection
    {
        return DailyCashClose::query()->with(['account', 'closer'])
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('close_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('close_date', '<=', $request->date('to')))
            ->latest('close_date')->lazyById(250)
            ->map(fn (DailyCashClose $close): array => [$close->account?->name, $close->close_date?->toDateString(), (float) $close->opening_cash, (float) $close->cash_in, (float) $close->cash_out, (float) $close->expected_closing_cash, (float) $close->actual_counted_cash, (float) $close->variance, $close->closer?->display_name ?? 'System', $close->closed_at?->toDateTimeString()]);
    }
}
