<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'avatar_path',
        'avatar_data',
        'avatar_mime',
        'first_name',
        'last_name',
        'username',
        'email',
        'email_verified_at',
        'phone',
        'language_id',
        'department_id',
        'role_id',
        'is_active',
        'must_change_password',
        'last_login_at',
        'last_login_ip',
        'created_by',
        'updated_by',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'avatar_data',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Resolve the current RBAC role without allowing a legacy `role` column
     * to shadow the role_id relationship on older installations.
     */
    private function resolvedRole(): ?Role
    {
        if (! $this->getAttribute('role_id')) {
            return null;
        }

        $role = $this->relationLoaded('role')
            ? $this->getRelation('role')
            : $this->role()->first();

        return $role instanceof Role ? $role : null;
    }

    public function createdReservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'created_by');
    }

    public function posShifts(): HasMany
    {
        return $this->hasMany(PosShift::class, 'cashier_id');
    }

    public function posOrders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'cashier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }

    public function housekeepingTasks(): HasMany
    {
        return $this->hasMany(HousekeepingTask::class, 'assignee_id');
    }

    public function maintenanceTasks(): HasMany
    {
        return $this->hasMany(MaintenanceTask::class, 'assignee_id');
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees')->withPivot(['assigned_by', 'assigned_at']);
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function completedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'completed_by');
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function notificationPreferences(): HasOne
    {
        return $this->hasOne(UserNotificationPreference::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->last_name]))) ?: $this->name;
    }

    public function getInitialsAttribute(): string
    {
        return collect(preg_split('/\s+/', trim($this->display_name)))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('');
    }

    public function hasAvatar(): bool
    {
        if (filled($this->avatar_data) && filled($this->avatar_mime)) {
            return true;
        }

        return filled($this->avatar_path) && Storage::disk('public')->exists($this->avatar_path);
    }

    /**
     * Return the user's role name for both the current RBAC relation and
     * legacy installations that still expose a string `role` attribute.
     */
    public function roleName(): ?string
    {
        $role = $this->resolvedRole();

        if ($role) {
            return $role->name;
        }

        $legacyRole = $this->getRawOriginal('role');

        return is_string($legacyRole) && trim($legacyRole) !== '' ? trim($legacyRole) : null;
    }

    public function roleLabel(): ?string
    {
        $role = $this->resolvedRole();

        if ($role) {
            return $role->label ?: $role->name;
        }

        return $this->roleName();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->roleName() === 'super_administrator') {
            return true;
        }

        $role = $this->resolvedRole();
        if (! $role instanceof Role) {
            return false;
        }

        if (! $role->relationLoaded('permissions')) {
            $role->load('permissions');
        }

        return $role->permissions->contains('name', $permission);
    }
}
