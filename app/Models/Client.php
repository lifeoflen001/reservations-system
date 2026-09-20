<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'email', 'phone', 'alternate_phone', 'country', 'city',
        'postal_code', 'nationality', 'date_of_birth', 'gender', 'document_type', 'document_number',
        'document_expiry', 'address', 'notes', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array { return ['date_of_birth' => 'date', 'document_expiry' => 'date', 'is_active' => 'boolean']; }

    public function reservations(): HasMany { return $this->hasMany(Reservation::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function totalSpent(): float { return app(\App\Services\FinancialService::class)->clientTotalSpent($this); }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    public function getInitialsAttribute(): string { return collect(preg_split('/\s+/', $this->full_name))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode(''); }
}
