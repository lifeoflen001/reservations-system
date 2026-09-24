<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosAudit extends Model
{
    protected $table = 'pos_audits';
    protected $fillable = ['event', 'actor_id', 'order_id', 'shift_id', 'product_id', 'metadata'];
    protected function casts(): array { return ['metadata' => 'array']; }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function order(): BelongsTo { return $this->belongsTo(PosOrder::class, 'order_id'); }
    public function shift(): BelongsTo { return $this->belongsTo(PosShift::class, 'shift_id'); }
    public function product(): BelongsTo { return $this->belongsTo(PosProduct::class, 'product_id'); }
}
