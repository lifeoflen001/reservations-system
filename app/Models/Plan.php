<?php

namespace App\Models;

use App\Services\EntitlementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['uuid', 'code', 'slug', 'name', 'description', 'status', 'is_public', 'is_system', 'sort_order', 'is_onboarding_eligible', 'is_onboarding_default'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'is_system' => 'boolean', 'is_onboarding_eligible' => 'boolean', 'is_onboarding_default' => 'boolean', 'sort_order' => 'integer'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot(['enabled', 'configuration'])
            ->withTimestamps();
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function limits(): HasMany
    {
        return $this->hasMany(PlanLimit::class);
    }

    protected static function booted(): void
    {
        static::saved(fn (self $plan) => app(EntitlementService::class)->forgetForPlan($plan->getKey()));
        static::deleted(fn (self $plan) => app(EntitlementService::class)->forgetForPlan($plan->getKey()));
    }
}
