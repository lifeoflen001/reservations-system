<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'client_id', 'room_id', 'reservation_source_id', 'created_by', 'updated_by', 'check_in',
        'check_out', 'adults', 'children', 'nightly_rate', 'total_amount', 'status', 'notes',
        'checked_in_at', 'checked_out_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
        'no_show_at', 'no_show_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'no_show_at' => 'datetime',
            'nightly_rate' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function source(): BelongsTo { return $this->belongsTo(ReservationSource::class, 'reservation_source_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function canceller(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function noShowBy(): BelongsTo { return $this->belongsTo(User::class, 'no_show_by'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function posOrders(): HasMany { return $this->hasMany(PosOrder::class); }
    public function posRoomCharges(): HasMany { return $this->hasMany(PosRoomCharge::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }

    public function scopeBlockingAvailability(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReservationStatus::Pending->value,
            ReservationStatus::Confirmed->value,
            ReservationStatus::CheckedIn->value,
        ]);
    }

    public function paidAmount(): float
    {
        return app(\App\Services\FinancialService::class)->paidAmount($this);
    }

    public function balance(): float
    {
        return app(\App\Services\FinancialService::class)->balance($this);
    }

    public function nights(): int
    {
        return max(0, $this->check_in->copy()->startOfDay()->diffInDays($this->check_out->copy()->startOfDay()));
    }
}
