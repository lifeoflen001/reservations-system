<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosPayment extends Model
{
    protected $table = 'pos_payments';
    protected $fillable = ['order_id', 'method', 'amount', 'reference', 'status', 'created_by', 'paid_at'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'paid_at' => 'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(PosOrder::class, 'order_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
