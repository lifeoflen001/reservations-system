<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    protected $fillable = ['payment_id', 'account_id', 'amount', 'method', 'refund_reference', 'reason', 'status', 'refunded_by', 'refunded_at', 'ledger_transaction_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'refunded_at' => 'datetime'];
    }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function account(): BelongsTo { return $this->belongsTo(FinancialAccount::class); }
    public function refunder(): BelongsTo { return $this->belongsTo(User::class, 'refunded_by'); }
    public function ledgerTransaction(): BelongsTo { return $this->belongsTo(FinancialTransaction::class, 'ledger_transaction_id'); }
}
