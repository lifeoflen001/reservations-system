<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosRoomCharge extends Model
{
    protected $table = 'pos_room_charges';
    protected $fillable = ['order_id', 'reservation_id', 'client_id', 'room_id', 'amount', 'status', 'posted_at', 'voided_by', 'voided_at', 'void_reason'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'posted_at' => 'datetime', 'voided_at' => 'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(PosOrder::class, 'order_id'); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function voider(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }
}
