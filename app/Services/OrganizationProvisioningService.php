<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationAuditLog;
use App\Models\OrganizationMembership;
use App\Models\OrganizationOnboarding;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OrganizationProvisioningService
{
    public function createFor(User $owner, array $data): OrganizationOnboarding
    {
        $existing = OrganizationOnboarding::query()->where('owner_user_id', $owner->getKey())->whereNull('completed_at')->first();
        if ($existing) return $existing->load('organization');

        return DB::transaction(function () use ($owner, $data): OrganizationOnboarding {
            $name = trim((string) $data['name']);
            $slug = Str::slug($name) ?: 'organization';
            $base = $slug;
            $suffix = 1;
            while (Organization::query()->where('slug', $slug)->exists()) $slug = $base.'-'.$suffix++;

            $organization = Organization::create([
                'uuid' => (string) Str::uuid(), 'name' => $name, 'slug' => $slug,
                'status' => 'active', 'country' => $data['country'] ?? null,
                'timezone' => $data['timezone'] ?? config('hotel.defaults.timezone'),
                'default_currency' => $data['currency'] ?? null,
                'billing_email' => $data['billing_email'] ?? $owner->email,
                'subscription_status' => 'trialing',
            ]);

            $role = Role::query()->where('name', 'administrator')->where('is_active', true)->first()
                ?: Role::query()->where('is_active', true)->orderBy('id')->first();
            if (! $role) throw ValidationException::withMessages(['name' => 'Lodgix roles are not configured yet. Please contact an administrator.']);

            $membership = OrganizationMembership::create([
                'organization_id' => $organization->getKey(), 'user_id' => $owner->getKey(),
                'role_id' => $role->getKey(), 'is_owner' => true, 'status' => 'active', 'joined_at' => now(),
            ]);
            $onboarding = OrganizationOnboarding::create([
                'organization_id' => $organization->getKey(), 'owner_user_id' => $owner->getKey(),
                'current_step' => 'plan', 'organization_completed' => true,
            ]);

            OrganizationAuditLog::create(['organization_id' => $organization->getKey(), 'actor_user_id' => $owner->getKey(), 'target_user_id' => $owner->getKey(), 'action' => 'organization.created', 'target_type' => Organization::class, 'target_id' => $organization->getKey()]);
            OrganizationAuditLog::create(['organization_id' => $organization->getKey(), 'actor_user_id' => $owner->getKey(), 'target_user_id' => $owner->getKey(), 'action' => 'organization.owner_membership.created', 'target_type' => OrganizationMembership::class, 'target_id' => $membership->getKey()]);
            OrganizationAuditLog::create(['organization_id' => $organization->getKey(), 'actor_user_id' => $owner->getKey(), 'action' => 'onboarding.started', 'target_type' => OrganizationOnboarding::class, 'target_id' => $onboarding->getKey()]);

            return $onboarding->load('organization');
        });
    }
}
