<?php

namespace App\Models;

use App\Services\EntitlementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class Subscription extends Model
{
    public const STATUSES = ['trialing', 'active', 'past_due', 'grace_period', 'suspended', 'cancelled'];

    protected $fillable = [
        'organization_id', 'plan_id', 'status', 'trial_ends_at', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function hasStatus(string $status): bool
    {
        return $this->status === $status;
    }

    protected static function booted(): void
    {
        static::saving(function (self $subscription): void {
            if (! in_array($subscription->status, self::STATUSES, true)) {
                throw new InvalidArgumentException('Unsupported subscription status.');
            }
        });
        static::saved(function (self $subscription): void {
            app(EntitlementService::class)->forgetForOrganization($subscription->organization_id);
        });
    }
}
