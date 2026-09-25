<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceReconciliation extends Model
{
    protected $fillable = ['account_id', 'period_start', 'period_end', 'opening_balance', 'statement_balance', 'system_balance', 'difference', 'status', 'completed_by', 'completed_at', 'notes'];
    protected function casts(): array { return ['period_start' => 'date', 'period_end' => 'date', 'opening_balance' => 'decimal:2', 'statement_balance' => 'decimal:2', 'system_balance' => 'decimal:2', 'difference' => 'decimal:2', 'completed_at' => 'datetime']; }
    public function account(): BelongsTo { return $this->belongsTo(FinancialAccount::class); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
}
