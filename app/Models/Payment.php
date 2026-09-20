<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = [
        'invoice_number', 'reservation_id', 'client_id', 'created_by', 'updated_by', 'amount', 'method',
        'reference', 'transaction_date', 'notes', 'status', 'voided_by', 'voided_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'datetime', 'status' => PaymentStatus::class, 'voided_at' => 'datetime'];
    }

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function voider(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }
    public function invoice(): HasOne { return $this->hasOne(Invoice::class); }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Paid->value);
    }
}
