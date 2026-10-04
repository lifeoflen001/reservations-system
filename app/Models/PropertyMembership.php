<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PropertyMembership extends Model
{
    protected $fillable = ['membership_id', 'property_id', 'access_level', 'status'];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'membership_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $access): void {
            $membership = $access->membership()->first();
            $property = $access->property()->first();

            if (! $membership || ! $property || (int) $membership->organization_id !== (int) $property->organization_id) {
                throw ValidationException::withMessages([
                    'property_id' => 'The property must belong to the membership organization.',
                ]);
            }
        });
    }
}
