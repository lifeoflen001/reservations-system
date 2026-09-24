<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PosOrder extends Model
{
    protected $table = 'pos_orders';
    protected $fillable = ['order_number', 'idempotency_key', 'outlet_id', 'cashier_id', 'shift_id', 'client_id', 'reservation_id', 'room_id', 'status', 'subtotal', 'discount_total', 'tax_total', 'total', 'notes', 'completed_at', 'voided_by', 'voided_at', 'void_reason'];
    protected function casts(): array { return ['subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'completed_at' => 'datetime', 'voided_at' => 'datetime']; }
    public function outlet(): BelongsTo { return $this->belongsTo(PosOutlet::class, 'outlet_id'); }
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'cashier_id'); }
    public function shift(): BelongsTo { return $this->belongsTo(PosShift::class, 'shift_id'); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function items(): HasMany { return $this->hasMany(PosOrderItem::class, 'order_id'); }
    public function payments(): HasMany { return $this->hasMany(PosPayment::class, 'order_id'); }
    public function roomCharge(): HasOne { return $this->hasOne(PosRoomCharge::class, 'order_id'); }
    public function refunds(): HasMany { return $this->hasMany(PosRefund::class, 'order_id'); }
    public function audits(): HasMany { return $this->hasMany(PosAudit::class, 'order_id'); }
}
