<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrganizationInvitation extends Model
{
    public const STATUSES = ['pending', 'accepted', 'expired', 'revoked'];

    protected $fillable = [
        'uuid', 'organization_id', 'role_id', 'email', 'status', 'token_hash',
        'expires_at', 'invited_by', 'accepted_at', 'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
    public function inviter(): BelongsTo { return $this->belongsTo(User::class, 'invited_by'); }
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'organization_invitation_properties', 'invitation_id', 'property_id')->withTimestamps();
    }

    public function isUsable(): bool
    {
        return $this->status === 'pending' && $this->expires_at?->isFuture();
    }
}
