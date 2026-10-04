<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Validation\ValidationException;

final class SubscriptionBootstrapService
{
    public function createFor(Organization $organization, Plan $plan): Subscription
    {
        if ($plan->status !== 'active' || ! $plan->is_public || ! $plan->is_onboarding_eligible || $plan->is_system) {
            throw ValidationException::withMessages(['plan_id' => 'That plan is not available for new Lodgix accounts.']);
        }
        if ($plan->limits()->where('key', 'properties')->whereNotNull('value')->where('value', '<', 1)->exists()) {
            throw ValidationException::withMessages(['plan_id' => 'The selected plan cannot provision the required first property.']);
        }

        $existing = $organization->subscriptions()->latest('id')->first();
        if ($existing && $existing->plan_id === $plan->getKey()) {
            return $existing;
        }

        $existing?->update(['ends_at' => now()]);

        return Subscription::create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $plan->getKey(),
            // SAAS-09 grants onboarding access; trial duration/expiry belongs
            // to SAAS-10 and is intentionally not invented here.
            'status' => 'trialing',
            'starts_at' => now(),
        ]);
    }
}
