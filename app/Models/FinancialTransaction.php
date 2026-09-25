<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    protected $fillable = ['transaction_number', 'account_id', 'transaction_type', 'direction', 'amount', 'currency', 'reference', 'description', 'transaction_date', 'source_type', 'source_id', 'created_by', 'approved_by', 'status', 'reversed_at', 'reversal_transaction_id', 'metadata'];

    protected function casts(): array { return ['amount' => 'decimal:2', 'transaction_date' => 'datetime', 'reversed_at' => 'datetime', 'metadata' => 'array']; }

    public function account(): BelongsTo { return $this->belongsTo(FinancialAccount::class, 'account_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function reversal(): BelongsTo { return $this->belongsTo(self::class, 'reversal_transaction_id'); }
}
