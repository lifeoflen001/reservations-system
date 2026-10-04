<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAuditLog extends Model
{
    protected $fillable = [
        'uuid', 'platform_administrator_id', 'action', 'target_type', 'target_id',
        'organization_id', 'property_id', 'metadata', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
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
}
