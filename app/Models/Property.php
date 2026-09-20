<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Property extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'address', 'city', 'country', 'base_currency_id',
        'default_language', 'check_in_time', 'check_out_time', 'timezone', 'setup_completed_at', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['setup_completed_at' => 'datetime'];
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
