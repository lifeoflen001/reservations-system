<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = ['expense_number', 'category_id', 'account_id', 'department_id', 'amount', 'currency', 'payment_method', 'payee', 'reference', 'description', 'attachment_path', 'expense_date', 'status', 'created_by', 'approved_by', 'paid_at', 'ledger_transaction_id'];

    protected function casts(): array { return ['amount' => 'decimal:2', 'expense_date' => 'datetime', 'paid_at' => 'datetime']; }

    public function category(): BelongsTo { return $this->belongsTo(ExpenseCategory::class, 'category_id'); }
    public function account(): BelongsTo { return $this->belongsTo(FinancialAccount::class, 'account_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function ledgerTransaction(): BelongsTo { return $this->belongsTo(FinancialTransaction::class, 'ledger_transaction_id'); }
}
