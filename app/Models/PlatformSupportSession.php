<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSupportSession extends Model
{
    protected $fillable = [
        'uuid', 'platform_administrator_id', 'organization_id', 'property_id', 'reason', 'status',
        'started_at', 'entered_at', 'last_activity_at', 'expires_at', 'ended_at', 'ended_by', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'entered_at' => 'datetime', 'last_activity_at' => 'datetime', 'expires_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(PlatformAdministrator::class, 'platform_administrator_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdministrator::class, 'ended_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at?->isFuture();
    }
}
