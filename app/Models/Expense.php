<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = ['expense_number', 'category_id', 'account_id', 'department_id', 'amount', 'currency', 'payment_method', 'payee', 'reference', 'description', 'attachment_path', 'attachment_type', 'expense_date', 'status', 'created_by', 'submitted_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason', 'paid_at', 'ledger_transaction_id', 'reversed_by', 'reversed_at', 'reversal_reason', 'reversal_transaction_id'];

    protected function casts(): array { return ['amount' => 'decimal:2', 'expense_date' => 'datetime', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'paid_at' => 'datetime', 'reversed_at' => 'datetime']; }

    public function category(): BelongsTo { return $this->belongsTo(ExpenseCategory::class, 'category_id'); }
    public function account(): BelongsTo { return $this->belongsTo(FinancialAccount::class, 'account_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function rejector(): BelongsTo { return $this->belongsTo(User::class, 'rejected_by'); }
    public function reverser(): BelongsTo { return $this->belongsTo(User::class, 'reversed_by'); }
    public function ledgerTransaction(): BelongsTo { return $this->belongsTo(FinancialTransaction::class, 'ledger_transaction_id'); }
}
