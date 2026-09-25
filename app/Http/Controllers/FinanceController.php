<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DailyCashClose;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\FinanceReconciliation;
use App\Models\FundTransfer;
use App\Services\FinanceService;
use App\Services\FinanceExportService;
use App\Services\PropertySettingsService;
use App\Support\CurrencyFormatter;
use App\Support\TablePagination;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Illuminate\Support\Facades\Storage;

class FinanceController extends Controller
{
    public function __construct(private readonly FinanceService $finance, private readonly FinanceExportService $exports) {}

    public function overview(Request $request, CurrencyFormatter $formatter)
    {
        $this->allow('finance.view');
        $accounts = $this->accountCollection();
        $today = now()->startOfDay();
        $moneyIn = FinancialTransaction::query()->where('direction', 'credit')->whereIn('status', ['posted', 'reversed'])->whereBetween('transaction_date', [$today, $today->copy()->endOfDay()])->sum('amount');
        $moneyOut = FinancialTransaction::query()->where('direction', 'debit')->whereIn('status', ['posted', 'reversed'])->whereBetween('transaction_date', [$today, $today->copy()->endOfDay()])->sum('amount');
        $period = in_array($request->input('period'), ['today', '7', '30', 'custom'], true) ? $request->input('period') : '30';
        $flowStart = match ($period) {
            'today' => now()->startOfDay(),
            '7' => now()->subDays(6)->startOfDay(),
            'custom' => $request->date('from')?->startOfDay() ?? now()->subDays(29)->startOfDay(),
            default => now()->subDays(29)->startOfDay(),
        };
        $flowEnd = $period === 'custom' && $request->date('to') ? $request->date('to')->endOfDay() : now()->endOfDay();
        if ($flowEnd->lt($flowStart)) [$flowStart, $flowEnd] = [$flowEnd->copy()->startOfDay(), $flowStart->copy()->endOfDay()];
        $flowDays = min(90, max(1, $flowStart->diffInDays($flowEnd) + 1));
        $flowRows = FinancialTransaction::query()->whereIn('status', ['posted', 'reversed'])->whereBetween('transaction_date', [$flowStart, $flowEnd])->get(['transaction_date', 'direction', 'amount']);
        $cashFlow = collect(range(0, $flowDays - 1))->map(function (int $offset) use ($flowRows, $flowStart): array {
            $date = $flowStart->copy()->addDays($offset);
            $rows = $flowRows->filter(fn (FinancialTransaction $row) => $row->transaction_date?->isSameDay($date));
            $in = round((float) $rows->where('direction', 'credit')->sum('amount'), 2);
            $out = round((float) $rows->where('direction', 'debit')->sum('amount'), 2);
            return ['date' => $date->format('M j'), 'money_in' => $in, 'money_out' => $out, 'net' => round($in - $out, 2)];
        });
        $lastMovements = FinancialTransaction::query()->whereIn('status', ['posted', 'reversed'])->whereIn('account_id', $accounts->pluck('id'))->latest('transaction_date')->get(['account_id', 'transaction_date'])->groupBy('account_id')->map(fn ($rows) => $rows->first()->transaction_date);
        $pendingReconciliations = FinanceReconciliation::query()->where('status', 'pending')->count();
        $pendingApprovals = Expense::query()->where('status', 'pending_approval')->count();
        $cashVariance = DailyCashClose::query()->where('variance', '<>', 0)->latest('close_date')->first();
        return view('finance.overview', ['accounts' => $accounts, 'formatter' => $formatter, 'moneyIn' => $moneyIn, 'moneyOut' => $moneyOut, 'net' => round($moneyIn - $moneyOut, 2), 'recent' => FinancialTransaction::with(['account', 'creator'])->whereIn('status', ['posted', 'reversed'])->latest('transaction_date')->limit(8)->get(), 'outstanding' => app(\App\Services\FinancialService::class)->outstandingBalance(), 'cashFlow' => $cashFlow, 'lastMovements' => $lastMovements, 'pendingReconciliations' => $pendingReconciliations, 'pendingApprovals' => $pendingApprovals, 'cashVariance' => $cashVariance, 'period' => $period, 'flowFrom' => $flowStart->toDateString(), 'flowTo' => $flowEnd->toDateString()]);
    }

    public function transactions(Request $request, CurrencyFormatter $formatter)
    {
        $this->allow('finance.view');
        $transactions = FinancialTransaction::query()->with(['account', 'creator'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->input('search')).'%';
                $query->where(fn ($nested) => $nested->where('transaction_number', 'like', $term)->orWhere('reference', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when($request->filled('account_id'), fn ($query) => $query->where('account_id', $request->integer('account_id')))
            ->when($request->filled('type') && $request->input('type') !== 'all', fn ($query) => $query->where('transaction_type', $request->input('type')))
            ->when($request->filled('status') && $request->input('status') !== 'all', fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('direction') && $request->input('direction') !== 'all', fn ($query) => $query->where('direction', $request->input('direction')))
            ->when($request->input('date') === 'today', fn ($query) => $query->whereDate('transaction_date', now()->toDateString()))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('transaction_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('transaction_date', '<=', $request->date('to')))
            ->latest('transaction_date')->latest('id')->paginate(TablePagination::perPage($request, 25))->withQueryString();
        return view('finance.transactions', ['transactions' => $transactions, 'accounts' => $this->accountCollection(), 'formatter' => $formatter, 'types' => FinancialTransaction::query()->select('transaction_type')->distinct()->orderBy('transaction_type')->pluck('transaction_type')]);
    }

    public function expenses(Request $request, CurrencyFormatter $formatter)
    {
        $this->allow('finance.expenses.view');
        $expenses = Expense::with(['account', 'category', 'department', 'creator', 'approver', 'rejector', 'reverser'])->latest('expense_date')->paginate(TablePagination::perPage($request, 25))->withQueryString();
        return view('finance.expenses', ['expenses' => $expenses, 'accounts' => $this->accountCollection(), 'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(), 'departments' => Department::where('is_active', true)->orderBy('name')->get(), 'formatter' => $formatter]);
    }

    public function expenseStore(Request $request)
    {
        $this->allow('finance.expenses.create');
        $data = $request->validate(['account_id' => ['required', 'integer', 'exists:financial_accounts,id'], 'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'], 'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'], 'expense_date' => ['required', 'date'], 'payment_method' => ['nullable', 'string', 'max:60'], 'payee' => ['nullable', 'string', 'max:160'], 'reference' => ['nullable', 'string', 'max:120'], 'description' => ['required', 'string', 'max:2000'], 'attachment_type' => ['nullable', Rule::in(['receipt', 'supplier_invoice', 'bank_proof', 'supporting_document'])], 'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:10240']]);
        if ($request->hasFile('attachment')) $data['attachment_path'] = $request->file('attachment')->store('finance/expenses');
        $data['status'] = $request->boolean('save_as_draft') ? 'draft' : 'pending_approval';
        if ($data['status'] === 'pending_approval') $this->allow('finance.expenses.submit');
        $this->finance->createExpense($data, $request->user()->getKey());
        return back()->with('success', $data['status'] === 'draft' ? 'Expense saved as a draft.' : 'Expense submitted for approval. No ledger movement is created until it is approved and paid.');
    }

    public function expenseAction(Request $request, Expense $expense, string $action)
    {
        $permission = match ($action) { 'submit' => 'finance.expenses.submit', 'approve' => 'finance.expenses.approve', 'reject' => 'finance.expenses.reject', 'pay' => 'finance.expenses.pay', 'reverse' => 'finance.expenses.reverse', default => abort(404) };
        $this->allow($permission);
        try {
            match ($action) {
                'submit' => $this->finance->submitExpense($expense, $request->user()->id),
                'approve' => $this->finance->approveExpense($expense, $request->user()->id),
                'reject' => $this->finance->rejectExpense($expense, $request->user()->id, $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason']),
                'pay' => $this->finance->payExpense($expense, $request->user()->id),
                'reverse' => $this->finance->reverseExpense($expense, $request->user()->id, $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason']),
            };
        } catch (InvalidArgumentException $exception) { return back()->with('error', $exception->getMessage()); }
        return back()->with('success', 'Expense '.$action.' action completed.');
    }

    public function attachment(Expense $expense)
    {
        $this->allow('finance.expenses.view');
        abort_unless($expense->attachment_path && Storage::disk('local')->exists($expense->attachment_path), 404);
        return Storage::disk('local')->download($expense->attachment_path, basename($expense->attachment_path));
    }

    public function transfers(Request $request, CurrencyFormatter $formatter)
    {
        $this->allow('finance.view');
        return view('finance.transfers', ['transfers' => FundTransfer::with(['fromAccount', 'toAccount', 'creator'])->latest('transfer_date')->paginate(TablePagination::perPage($request, 25))->withQueryString(), 'accounts' => $this->accountCollection(), 'formatter' => $formatter]);
    }

    public function transferStore(Request $request)
    {
        $this->allow('finance.transfers.create');
        $data = $request->validate(['from_account_id' => ['required', 'integer', 'exists:financial_accounts,id'], 'to_account_id' => ['required', 'integer', 'exists:financial_accounts,id', 'different:from_account_id'], 'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'], 'transfer_date' => ['required', 'date'], 'reference' => ['nullable', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'attachment' => ['nullable', 'file', 'max:10240']]);
        if ($request->hasFile('attachment')) $data['attachment_path'] = $request->file('attachment')->store('finance/transfers');
        try { $this->finance->createTransfer($data, $request->user()->getKey()); }
        catch (InvalidArgumentException $exception) { return back()->withInput()->with('error', $exception->getMessage()); }
        return back()->with('success', 'Internal transfer posted atomically to both accounts.');
    }

    public function accountStore(Request $request)
    {
        $this->allow('finance.accounts.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:financial_accounts,code'], 'type' => ['required', Rule::in(['cash', 'bank', 'mobile_money', 'card_clearing', 'petty_cash', 'other'])], 'bank_name' => ['nullable', 'string', 'max:120'], 'account_number' => ['nullable', 'string', 'max:120'], 'branch' => ['nullable', 'string', 'max:120'], 'opening_balance' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'], 'opening_date' => ['nullable', 'date']]);
        $account = FinancialAccount::create(collect($data)->except(['opening_balance', 'opening_date'])->all() + ['currency' => app(PropertySettingsService::class)->currency()->code, 'is_active' => true]);
        if ((float) ($data['opening_balance'] ?? 0) > 0) $this->finance->postOpeningBalance($account, (float) $data['opening_balance'], $request->user()->id, $data['opening_date'] ?? now());
        return back()->with('success', 'Financial account created. Use an opening-balance ledger entry to initialize it.');
    }

    public function accounts(CurrencyFormatter $formatter)
    {
        $this->allow('finance.accounts.view');
        return view('finance.accounts', ['accounts' => $this->accountCollection(), 'formatter' => $formatter]);
    }

    public function reports(CurrencyFormatter $formatter)
    {
        $this->allow('finance.reports.view');
        $expenses = Expense::with(['category', 'department'])->whereIn('status', ['paid', 'posted'])->latest('expense_date')->limit(100)->get();
        return view('finance.reports', ['formatter' => $formatter, 'accounts' => $this->accountCollection(), 'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(), 'expenses' => $expenses, 'expenseByCategory' => $expenses->groupBy(fn (Expense $expense) => $expense->category?->name ?? 'Uncategorised')->map(fn ($rows) => $rows->sum('amount')), 'expenseByDepartment' => $expenses->groupBy(fn (Expense $expense) => $expense->department?->name ?? 'Unassigned')->map(fn ($rows) => $rows->sum('amount'))]);
    }

    public function export(Request $request, string $report)
    {
        $this->allow('finance.reports.export');
        abort_unless(in_array($report, FinanceExportService::REPORTS, true), 404);
        $format = $request->string('format', 'csv')->toString();
        abort_unless(in_array($format, ['csv', 'pdf', 'print'], true), 422);
        $this->exports->audit($request, $report, $format);
        $headers = $this->exports->headers($report);
        $rows = $this->exports->rows($request, $report);
        $filename = $this->exports->filename($request, $report, $format);
        $logoPath = public_path('assets/branding/lodgix-mark.png');
        $logoData = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : null;
        if ($format === 'pdf') {
            abort_unless(class_exists(\Dompdf\Dompdf::class), 503, 'PDF export is not available.');
            $materialized = $rows->take(10000)->values()->all();
            $html = view('finance.exports.document', ['title' => str_replace('-', ' ', ucfirst($report)), 'headers' => $headers, 'rows' => $materialized, 'generatedBy' => $request->user()?->display_name ?? 'System', 'from' => $request->input('from'), 'to' => $request->input('to'), 'logoData' => $logoData])->render();
            $pdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true]);
            $pdf->loadHtml($html, 'UTF-8'); $pdf->setPaper('A4', 'landscape'); $pdf->render();
            return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"']);
        }
        if ($format === 'print') return response(view('finance.exports.document', ['title' => str_replace('-', ' ', ucfirst($report)), 'headers' => $headers, 'rows' => $rows->take(10000)->values()->all(), 'generatedBy' => $request->user()?->display_name ?? 'System', 'from' => $request->input('from'), 'to' => $request->input('to'), 'logoData' => $logoData])->render());
        return response()->streamDownload(function () use ($rows, $headers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) fputcsv($handle, $row);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function pettyCash(CurrencyFormatter $formatter)
    {
        $this->allow('finance.petty_cash.manage');
        $account = FinancialAccount::query()->where('code', 'petty_cash')->firstOrFail();
        $transactions = $account->transactions()->whereIn('status', ['posted', 'reversed'])->latest('transaction_date')->paginate(TablePagination::perPage(request(), 20))->withQueryString();
        $dayStart = now()->startOfDay();
        $dayEnd = now()->endOfDay();
        $dayTransactions = $account->transactions()->whereIn('status', ['posted', 'reversed'])->whereBetween('transaction_date', [$dayStart, $dayEnd]);
        $cashInToday = (float) (clone $dayTransactions)->where('direction', 'credit')->sum('amount');
        $cashOutToday = (float) (clone $dayTransactions)->where('direction', 'debit')->sum('amount');
        $openingCash = (float) $account->transactions()->whereIn('status', ['posted', 'reversed'])->where('transaction_date', '<', $dayStart)->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as balance")->value('balance');
        $lastClose = DailyCashClose::where('account_id', $account->id)->latest('close_date')->first();
        return view('finance.petty-cash', ['account' => $account, 'transactions' => $transactions, 'balance' => $this->finance->balance($account), 'formatter' => $formatter, 'closes' => DailyCashClose::where('account_id', $account->id)->latest('close_date')->limit(10)->get(), 'cashInToday' => round($cashInToday, 2), 'cashOutToday' => round($cashOutToday, 2), 'openingCash' => round($openingCash, 2), 'expectedCash' => round($openingCash + $cashInToday - $cashOutToday, 2), 'lastClose' => $lastClose]);
    }

    public function reconciliation(CurrencyFormatter $formatter)
    {
        $this->allow('finance.reconcile');
        return view('finance.reconciliation', ['accounts' => $this->accountCollection()->whereIn('type', ['bank', 'card_clearing']), 'reconciliations' => FinanceReconciliation::with('account')->latest()->limit(20)->get(), 'formatter' => $formatter]);
    }

    public function reconciliationStore(Request $request)
    {
        $this->allow('finance.reconcile');
        $data = $request->validate(['account_id' => ['required', 'integer', 'exists:financial_accounts,id'], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start'], 'opening_balance' => ['required', 'numeric', 'decimal:0,2'], 'statement_balance' => ['required', 'numeric', 'decimal:0,2'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $periodStart = Carbon::parse($data['period_start'])->startOfDay();
        $periodEnd = Carbon::parse($data['period_end'])->endOfDay();
        $movement = (float) FinancialTransaction::query()->where('account_id', $data['account_id'])->whereIn('status', ['posted', 'reversed'])->whereBetween('transaction_date', [$periodStart, $periodEnd])->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as balance")->value('balance');
        $system = round((float) $data['opening_balance'] + $movement, 2);
        $difference = round((float) $data['statement_balance'] - $system, 2);
        $matched = abs($difference) < 0.01;
        FinanceReconciliation::create($data + ['system_balance' => $system, 'difference' => $difference, 'status' => $matched ? 'completed' : 'pending', 'completed_by' => $matched ? $request->user()->id : null, 'completed_at' => $matched ? now() : null]);
        return back()->with($matched ? 'success' : 'warning', $matched ? 'Account reconciliation completed.' : 'Reconciliation saved as pending because the statement difference is not zero.');
    }

    public function cashCloseStore(Request $request)
    {
        $this->allow('finance.petty_cash.manage');
        $account = FinancialAccount::query()->where('code', 'petty_cash')->firstOrFail();
        $data = $request->validate(['close_date' => ['required', 'date'], 'actual_counted_cash' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $start = now()->parse($data['close_date'])->startOfDay();
        $end = $start->copy()->endOfDay();
        $cashIn = (float) $account->transactions()->whereIn('status', ['posted', 'reversed'])->where('direction', 'credit')->whereBetween('transaction_date', [$start, $end])->sum('amount');
        $cashOut = (float) $account->transactions()->whereIn('status', ['posted', 'reversed'])->where('direction', 'debit')->whereBetween('transaction_date', [$start, $end])->sum('amount');
        $opening = (float) FinancialTransaction::query()->where('account_id', $account->id)->whereIn('status', ['posted', 'reversed'])->where('transaction_date', '<', $start)->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as balance")->value('balance');
        $expected = round($opening + $cashIn - $cashOut, 2);
        DailyCashClose::updateOrCreate(['account_id' => $account->id, 'close_date' => $data['close_date']], ['opening_cash' => $opening, 'cash_in' => $cashIn, 'cash_out' => $cashOut, 'bank_deposits' => 0, 'expected_closing_cash' => $expected, 'actual_counted_cash' => $data['actual_counted_cash'], 'variance' => round((float) $data['actual_counted_cash'] - $expected, 2), 'closed_by' => $request->user()->id, 'closed_at' => now(), 'notes' => $data['notes'] ?? null]);
        return back()->with('success', 'Petty cash close saved with a ledger-derived expected balance.');
    }

    private function accountCollection()
    {
        return $this->finance->balances()->get()->each(function (FinancialAccount $account): void {
            $account->setAttribute('current_balance', round((float) ($account->credits ?? 0) - (float) ($account->debits ?? 0), 2));
            $account->setAttribute('masked_account_number', $account->account_number ? '••••'.substr((string) $account->account_number, -4) : null);
        });
    }

    private function allow(string $permission): void
    {
        abort_unless(auth()->user()?->hasPermission($permission), 403);
    }
}
