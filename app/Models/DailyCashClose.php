<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCashClose extends Model
{
    protected $fillable = ['account_id', 'close_date', 'opening_cash', 'cash_in', 'cash_out', 'bank_deposits', 'expected_closing_cash', 'actual_counted_cash', 'variance', 'closed_by', 'closed_at', 'notes'];
    protected function casts(): array { return ['close_date' => 'date', 'opening_cash' => 'decimal:2', 'cash_in' => 'decimal:2', 'cash_out' => 'decimal:2', 'bank_deposits' => 'decimal:2', 'expected_closing_cash' => 'decimal:2', 'actual_counted_cash' => 'decimal:2', 'variance' => 'decimal:2', 'closed_at' => 'datetime']; }
    public function account(): BelongsTo { return $this->belongsTo(FinancialAccount::class); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
}
