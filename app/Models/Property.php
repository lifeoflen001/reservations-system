<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'address', 'city', 'country', 'base_currency_id',
        'default_language', 'check_in_time', 'check_out_time', 'timezone', 'setup_completed_at', 'updated_by',
        'organization_id', 'uuid', 'slug', 'property_code', 'status', 'settings',
    ];

    protected static function booted(): void
    {
        static::creating(function (Property $property): void {
            if (blank($property->organization_id)) {
                throw new \LogicException('A property must be created with an explicit organization.');
            }
        });

        static::updating(function (Property $property): void {
            if ($property->isDirty('organization_id') && $property->getOriginal('organization_id') !== null) {
                throw new \LogicException('Property organization ownership is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['setup_completed_at' => 'datetime', 'settings' => 'array'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(PropertyMembership::class);
    }

    public function supportSessions(): HasMany
    {
        return $this->hasMany(PlatformSupportSession::class);
    }
}
