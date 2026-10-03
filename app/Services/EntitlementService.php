<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class EntitlementService
{
    public const ELIGIBLE_SUBSCRIPTION_STATUSES = ['trialing', 'active', 'grace_period'];

    public function __construct(private readonly UsageService $usage) {}

    public function currentOrganization(): ?Organization
    {
        return app(TenantContext::class)->currentOrganization();
    }

    public function hasFeature(Organization $organization, string $featureKey): bool
    {
        $snapshot = $this->snapshot($organization);
        return ($snapshot['features'][$featureKey] ?? false) === true;
    }

    public function limit(Organization $organization, string $limitKey): ?int
    {
        return $this->snapshot($organization)['limits'][$limitKey] ?? null;
    }

    public function usage(Organization $organization, string $limitKey): int
    {
        return $this->usage->for($organization, $limitKey);
    }

    public function remaining(Organization $organization, string $limitKey): ?int
    {
        $limit = $this->limit($organization, $limitKey);
        return $limit === null ? null : $limit - $this->usage($organization, $limitKey);
    }

    public function canConsume(Organization $organization, string $limitKey, int $increment = 1): bool
    {
        $limit = $this->limit($organization, $limitKey);
        return $limit === null || ($this->usage($organization, $limitKey) + $increment) <= $limit;
    }

    /** @return array{plan:?Plan, subscription:?Subscription, eligible:bool, features:array<string,bool>, limits:array<string,int|null>} */
    public function snapshot(Organization $organization): array
    {
        $subscription = $organization->subscriptions()->latest('id')->with(['plan.features', 'plan.limits'])->first();
        $plan = $subscription?->plan;
        $key = $this->cacheKey($organization, $subscription, $plan);

        $resolved = Cache::remember($key, now()->addMinutes(10), function () use ($subscription, $plan): array {
            $eligible = $subscription !== null
                && in_array($subscription->status, self::ELIGIBLE_SUBSCRIPTION_STATUSES, true)
                && $plan?->status === 'active';

            $features = [];
            foreach ($plan?->features ?? [] as $feature) {
                $features[$feature->key] = $eligible && $feature->status === 'active' && (bool) $feature->pivot->enabled;
            }
            $limits = [];
            foreach ($plan?->limits ?? [] as $limit) {
                $limits[$limit->key] = $eligible ? $limit->value : null;
            }

            return compact('eligible', 'features', 'limits');
        });

        return $resolved + ['plan' => $plan, 'subscription' => $subscription];
    }

    public function explainFeatureDenial(Organization $organization, string $featureKey): string
    {
        $snapshot = $this->snapshot($organization);
        if (! $snapshot['subscription']) return 'Your organization does not have an active Lodgix subscription entitlement.';
        if (! $snapshot['plan']) return 'Your organization subscription is not assigned to a plan.';
        if ($snapshot['plan']->status !== 'active' || ! $snapshot['eligible']) return 'Your organization subscription is not currently eligible for this feature.';
        $feature = Feature::query()->where('key', $featureKey)->first();
        return ($feature?->name ?: 'This feature').' is not included in your current Lodgix plan.';
    }

    public function forgetForOrganization(int|string|null $organizationId): void
    {
        if (! $organizationId) return;
        $organization = Organization::query()->find($organizationId);
        if ($organization) {
            $subscription = $organization->subscriptions()->latest('id')->with('plan')->first();
            Cache::forget($this->cacheKey($organization, $subscription, $subscription?->plan));
            $this->forgetMatching($organization->uuid);
        }
    }

    public function forgetForPlan(int|string|null $planId): void
    {
        if (! $planId) return;
        Organization::query()->whereHas('subscriptions', fn ($query) => $query->where('plan_id', $planId))->get()->each(function (Organization $organization): void {
            $subscription = $organization->subscriptions()->latest('id')->with('plan')->first();
            Cache::forget($this->cacheKey($organization, $subscription, $subscription?->plan));
            $this->forgetMatching($organization->uuid);
        });
    }

    private function forgetMatching(string $organizationUuid): void
    {
        // Versioned keys make updated timestamps invalidate future reads; this
        // explicit forget handles the common fixed-version cache used in tests.
        Cache::forget('entitlements:'.$organizationUuid);
    }

    private function cacheKey(Organization $organization, ?Subscription $subscription, ?Plan $plan): string
    {
        return 'entitlements:'.($organization->uuid ?: $organization->getKey()).':'.($subscription?->updated_at?->timestamp ?: 0).':'.($plan?->updated_at?->timestamp ?: 0);
    }
}
