<?php

namespace App\Models;

use App\Enums\OperatingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Installation extends Model
{
    protected $fillable = ['status', 'operating_mode', 'base_currency_id', 'completed_at'];

    protected function casts(): array
    {
        return ['operating_mode' => OperatingMode::class, 'completed_at' => 'datetime'];
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function isComplete(): bool
    {
        return $this->status === 'complete' && $this->completed_at !== null;
    }
}
