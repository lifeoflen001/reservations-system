<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'uuid', 'name', 'slug', 'status', 'country', 'timezone', 'default_currency',
        'billing_email', 'subscription_status', 'trial_ends_at',
    ];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime'];
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->withPivot(['role_id', 'is_owner', 'status', 'joined_at', 'invited_by'])
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(OrganizationAuditLog::class);
    }

    public function supportSessions(): HasMany
    {
        return $this->hasMany(PlatformSupportSession::class);
    }
}
