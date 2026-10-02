<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationMembership extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'role_id', 'status', 'joined_at', 'invited_by',
    ];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function propertyAccess(): HasMany
    {
        return $this->hasMany(PropertyMembership::class, 'membership_id');
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_memberships', 'membership_id', 'property_id')
            ->withPivot(['access_level', 'status'])
            ->withTimestamps();
    }
}
