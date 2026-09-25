<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundTransfer extends Model
{
    protected $fillable = ['transfer_number', 'from_account_id', 'to_account_id', 'amount', 'currency', 'reference', 'description', 'attachment_path', 'transfer_date', 'status', 'created_by', 'approved_by', 'debit_transaction_id', 'credit_transaction_id'];

    protected function casts(): array { return ['amount' => 'decimal:2', 'transfer_date' => 'datetime']; }

    public function fromAccount(): BelongsTo { return $this->belongsTo(FinancialAccount::class, 'from_account_id'); }
    public function toAccount(): BelongsTo { return $this->belongsTo(FinancialAccount::class, 'to_account_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function debitTransaction(): BelongsTo { return $this->belongsTo(FinancialTransaction::class, 'debit_transaction_id'); }
    public function creditTransaction(): BelongsTo { return $this->belongsTo(FinancialTransaction::class, 'credit_transaction_id'); }
}
