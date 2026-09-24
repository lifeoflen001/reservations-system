<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosShift extends Model
{
    protected $table = 'pos_shifts';
    protected $fillable = ['outlet_id', 'cashier_id', 'status', 'opening_cash', 'opened_at', 'cash_sales', 'expected_cash', 'actual_cash', 'variance', 'closed_at', 'notes'];
    protected function casts(): array { return ['opening_cash' => 'decimal:2', 'cash_sales' => 'decimal:2', 'expected_cash' => 'decimal:2', 'actual_cash' => 'decimal:2', 'variance' => 'decimal:2', 'opened_at' => 'datetime', 'closed_at' => 'datetime']; }
    public function outlet(): BelongsTo { return $this->belongsTo(PosOutlet::class, 'outlet_id'); }
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'cashier_id'); }
    public function orders(): HasMany { return $this->hasMany(PosOrder::class, 'shift_id'); }
    public function audits(): HasMany { return $this->hasMany(PosAudit::class, 'shift_id'); }
}
