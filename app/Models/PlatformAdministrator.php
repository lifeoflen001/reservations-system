<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Fortify\TwoFactorAuthenticatable;

class PlatformAdministrator extends Authenticatable
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const ACTIVE = 'active';
    public const DISABLED = 'disabled';
    public const DEFAULT_PERMISSIONS = [
        'platform.dashboard.view',
        'platform.organizations.view',
        'platform.organizations.manage',
        'platform.properties.view',
        'platform.subscriptions.view',
        'platform.plans.view',
        'platform.plans.manage',
        'platform.features.view',
        'platform.features.manage',
        'platform.subscriptions.assign_plan',
        'platform.support.start',
        'platform.support.view',
        'platform.health.view',
        'platform.audit.view',
        'platform.administrators.manage',
    ];

    protected $fillable = ['uuid', 'name', 'email', 'password', 'role', 'permissions', 'status', 'last_login_at', 'last_login_ip'];

    protected $hidden = ['password', 'remember_token', 'permissions', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function supportSessions(): HasMany
    {
        return $this->hasMany(PlatformSupportSession::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PlatformAuditLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function hasPlatformPermission(string $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->role === 'platform_admin'
            || in_array($permission, $this->permissions ?: [], true);
    }
}
