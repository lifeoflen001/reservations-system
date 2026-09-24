<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosRefund extends Model
{
    protected $table = 'pos_refunds';
    protected $fillable = ['order_id', 'amount', 'reason', 'refunded_by', 'approved_by', 'refunded_at'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'refunded_at' => 'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(PosOrder::class, 'order_id'); }
    public function refunder(): BelongsTo { return $this->belongsTo(User::class, 'refunded_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
