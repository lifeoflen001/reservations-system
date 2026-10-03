<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationAuditLog;
use App\Models\OrganizationOnboarding;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OnboardingService
{
    public function stateFor(User $user): ?OrganizationOnboarding
    {
        return OrganizationOnboarding::query()->with(['organization', 'organization.subscriptions.plan'])->where('owner_user_id', $user->getKey())->whereNull('completed_at')->latest('id')->first();
    }

    public function selectPlan(User $user, int $planId): OrganizationOnboarding
    {
        $onboarding = $this->required($user);
        $plan = \App\Models\Plan::query()->whereKey($planId)->where('status', 'active')->where('is_public', true)->where('is_onboarding_eligible', true)->where('is_system', false)->first();
        if (! $plan) throw ValidationException::withMessages(['plan_id' => 'Select an available signup plan.']);
        app(SubscriptionBootstrapService::class)->createFor($onboarding->organization, $plan);
        $onboarding->forceFill(['current_step' => 'property', 'plan_completed' => true])->save();
        return $onboarding->fresh(['organization', 'organization.subscriptions.plan']);
    }

    public function createProperty(User $user, array $data): Property
    {
        $onboarding = $this->required($user);
        if (! $onboarding->plan_completed) throw ValidationException::withMessages(['plan_id' => 'Choose a plan before creating a property.']);
        $organization = $onboarding->organization;
        return DB::transaction(function () use ($user, $data, $onboarding, $organization): Property {
            $existing = $organization->properties()->where('status', 'active')->first();
            if ($existing) {
                $onboarding->forceFill(['property_completed' => true, 'current_step' => 'hotel'])->save();
                return $existing;
            }
            $organization = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            app(UsageLimitService::class)->assertCanConsume($organization, 'properties');
            $slug = Str::slug((string) $data['name']) ?: 'property';
            $base = $slug; $suffix = 1;
            while (Property::query()->where('organization_id', $organization->getKey())->where('slug', $slug)->exists()) $slug = $base.'-'.$suffix++;
            $code = trim((string) $data['property_code']);
            $currency = \App\Models\Currency::query()->where('code', $data['currency'])->where('is_active', true)->first();
            if (! $currency) throw ValidationException::withMessages(['currency' => 'Select an active currency.']);
            $property = Property::create([
                'organization_id' => $organization->getKey(), 'uuid' => (string) Str::uuid(), 'slug' => $slug,
                'property_code' => $code, 'name' => trim((string) $data['name']), 'country' => $data['country'] ?? null,
                'timezone' => $data['timezone'] ?? $organization->timezone, 'base_currency_id' => $currency->getKey(),
                'email' => $data['email'] ?? $user->email, 'phone' => $data['phone'] ?? null,
                'check_in_time' => config('hotel.defaults.check_in_time'), 'check_out_time' => config('hotel.defaults.check_out_time'), 'status' => 'active',
            ]);
            $membership = $organization->memberships()->where('user_id', $user->getKey())->where('status', 'active')->firstOrFail();
            PropertyMembership::create(['membership_id' => $membership->getKey(), 'property_id' => $property->getKey(), 'access_level' => 'full', 'status' => 'active']);
            $onboarding->forceFill(['property_completed' => true, 'current_step' => 'hotel'])->save();
            OrganizationAuditLog::create(['organization_id' => $organization->getKey(), 'actor_user_id' => $user->getKey(), 'target_user_id' => $user->getKey(), 'action' => 'onboarding.first_property_created', 'target_type' => Property::class, 'target_id' => $property->getKey()]);
            return $property;
        });
    }

    public function finish(User $user): OrganizationOnboarding
    {
        $onboarding = $this->required($user);
        if (! $onboarding->organization_completed || ! $onboarding->plan_completed || ! $onboarding->property_completed) throw ValidationException::withMessages(['onboarding' => 'Complete the required setup steps before continuing.']);
        $onboarding->forceFill(['hotel_setup_completed' => true, 'current_step' => 'complete', 'completed_at' => now()])->save();
        OrganizationAuditLog::create(['organization_id' => $onboarding->organization_id, 'actor_user_id' => $user->getKey(), 'target_user_id' => $user->getKey(), 'action' => 'onboarding.completed', 'target_type' => OrganizationOnboarding::class, 'target_id' => $onboarding->getKey()]);
        $property = $onboarding->organization->properties()->where('status', 'active')->orderBy('id')->firstOrFail();
        app(TenantContext::class)->activate((int) $onboarding->organization_id, (int) $property->getKey());
        return $onboarding->fresh(['organization']);
    }

    private function required(User $user): OrganizationOnboarding
    {
        $state = OrganizationOnboarding::query()->with('organization')->where('owner_user_id', $user->getKey())->whereNull('completed_at')->latest('id')->first();
        if (! $state) throw ValidationException::withMessages(['onboarding' => 'Start your organization setup before continuing.']);
        return $state;
    }
}
