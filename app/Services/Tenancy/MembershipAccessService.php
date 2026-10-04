<?php

namespace App\Services\Tenancy;

use App\Models\Organization;
use App\Models\OrganizationAuditLog;
use App\Models\OrganizationMembership;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\UsageLimitService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MembershipAccessService
{
    public function __construct(private readonly TenantContext $context, private readonly UsageLimitService $limits) {}

    public function currentOrganizationFor(User $actor): Organization
    {
        $organization = $this->context->requireOrganization();
        $this->assertActorMembership($actor, $organization);

        return $organization;
    }

    public function hasCurrentOrganizationPermission(User $actor, string $permission): bool
    {
        $organizationId = $this->context->scopeOrganizationId();
        if ($organizationId === null) {
            return false;
        }

        $membership = OrganizationMembership::query()
            ->with('role.permissions')
            ->where('organization_id', $organizationId)
            ->where('user_id', $actor->getKey())
            ->where('status', 'active')
            ->first();

        if ($membership?->is_owner && in_array($permission, OrganizationOwnershipService::ownerManagementPermissions(), true)) {
            return true;
        }

        return (bool) $membership?->role?->permissions?->contains('name', $permission);
    }

    public function members(Organization $organization): Builder
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->with([
                'user:id,name,first_name,last_name,email,last_login_at,is_active',
                'role:id,name,label,is_active',
                'propertyAccess' => fn ($query) => $query->with('property:id,name,organization_id,status'),
            ])
            ->withCount(['propertyAccess as active_property_count' => fn ($query) => $query->where('status', 'active')])
            ->orderByDesc('status')
            ->orderBy('id');
    }

    public function membersForProperty(Property $property): Builder
    {
        return OrganizationMembership::query()
            ->where('organization_id', $property->organization_id)
            ->whereHas('propertyAccess', fn ($query) => $query->where('property_id', $property->getKey())->where('status', 'active'))
            ->with(['user:id,name,first_name,last_name,email', 'role:id,name,label,is_active'])
            ->orderBy('status')->orderBy('id');
    }

    public function assertActorMembership(User $actor, Organization $organization): OrganizationMembership
    {
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $actor->getKey())
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            throw new AuthorizationException('You do not have access to this organization.');
        }

        return $membership;
    }

    public function assertMember(OrganizationMembership $membership, Organization $organization): OrganizationMembership
    {
        if ((int) $membership->organization_id !== (int) $organization->getKey()) {
            throw new AuthorizationException('The member does not belong to the current organization.');
        }

        return $membership->load(['user', 'role', 'propertyAccess.property']);
    }

    /** @param array<int, int|string> $propertyIds */
    public function updateMembership(
        OrganizationMembership $membership,
        User $actor,
        ?int $roleId,
        string $status,
        array $propertyIds,
    ): OrganizationMembership {
        $organization = $this->currentOrganizationFor($actor);
        $membership = $this->assertMember($membership, $organization);
        $status = in_array($status, ['active', 'inactive'], true) ? $status : 'inactive';
        $propertyIds = collect($propertyIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all();

        if ($membership->user_id === $actor->getKey() && $status !== 'active') {
            throw ValidationException::withMessages(['status' => 'You cannot deactivate your own organization access.']);
        }

        if ($status !== 'active' && OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('status', 'active')
            ->where('id', '!=', $membership->getKey())
            ->doesntExist()) {
            throw ValidationException::withMessages(['status' => 'The organization must retain at least one active member.']);
        }

        if ($status !== 'active' && $membership->is_owner && OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('status', 'active')
            ->where('is_owner', true)
            ->whereKeyNot($membership->getKey())
            ->doesntExist()) {
            throw ValidationException::withMessages(['status' => 'The organization must retain at least one active owner.']);
        }

        $role = $roleId === null ? null : Role::query()->whereKey($roleId)->where('is_active', true)->first();
        if ($roleId !== null && ! $role) {
            throw ValidationException::withMessages(['role_id' => 'Select an active organization role.']);
        }

        if ($status === 'active' && $membership->status !== 'active') {
            $this->limits->assertCanConsume($organization, 'users');
        }

        $allowedProperties = Property::query()
            ->where('organization_id', $organization->getKey())
            ->where('status', 'active')
            ->whereIn('id', $propertyIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        if (count($allowedProperties) !== count($propertyIds)) {
            throw ValidationException::withMessages(['property_ids' => 'Every selected property must belong to the current organization.']);
        }

        $beforeRole = $membership->role?->label;
        $beforeStatus = $membership->status;
        $beforePropertyIds = $membership->propertyAccess->where('status', 'active')->pluck('property_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();

        return DB::transaction(function () use ($membership, $actor, $organization, $role, $roleId, $status, $allowedProperties, $beforeRole, $beforeStatus, $beforePropertyIds): OrganizationMembership {
            $membership->forceFill(['role_id' => $roleId, 'status' => $status])->save();

            $currentAccess = $membership->propertyAccess()->get()->keyBy('property_id');
            foreach ($allowedProperties as $propertyId) {
                $access = $currentAccess->get($propertyId) ?? new PropertyMembership([
                    'membership_id' => $membership->getKey(),
                    'property_id' => $propertyId,
                ]);
                $access->forceFill(['status' => 'active'])->save();
            }
            foreach ($currentAccess as $access) {
                if (! in_array((int) $access->property_id, $allowedProperties, true) && $access->status === 'active') {
                    $access->forceFill(['status' => 'inactive'])->save();
                }
            }

            $added = array_values(array_diff($allowedProperties, $beforePropertyIds));
            $removed = array_values(array_diff($beforePropertyIds, $allowedProperties));
            if ($beforeRole !== $role?->label || $beforeStatus !== $status) {
                $this->audit($organization, $actor, 'membership.updated', $membership->user_id, $membership, [
                    'role_before' => $beforeRole, 'role_after' => $role?->label,
                    'status_before' => $beforeStatus, 'status_after' => $status,
                ]);
            }
            if ($added !== []) {
                $this->audit($organization, $actor, 'property_access.granted', $membership->user_id, $membership, ['properties_added' => $added]);
            }
            if ($removed !== []) {
                $this->audit($organization, $actor, 'property_access.revoked', $membership->user_id, $membership, ['properties_removed' => $removed]);
            }

            if ((int) $membership->user_id === (int) $actor->getKey()) {
                $this->context->clearTenantSensitiveSessionState();
            }

            return $membership->fresh(['user', 'role', 'propertyAccess.property']);
        });
    }

    /** @param array<int, int|string> $propertyIds */
    public function syncPropertyAccess(Property $property, User $actor, array $propertyIds): void
    {
        $organization = $this->currentOrganizationFor($actor);
        if ((int) $property->organization_id !== (int) $organization->getKey()) {
            throw new AuthorizationException('The property does not belong to the current organization.');
        }

        $membershipIds = OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('status', 'active')
            ->pluck('id');
        $selected = collect($propertyIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $validSelected = $membershipIds->intersect($selected)->values();
        if ($validSelected->count() !== $selected->count()) {
            throw ValidationException::withMessages(['membership_ids' => 'Every selected member must belong to the current organization.']);
        }

        DB::transaction(function () use ($property, $actor, $organization, $membershipIds, $validSelected): void {
            $before = PropertyMembership::query()->where('property_id', $property->getKey())->whereIn('membership_id', $membershipIds)->where('status', 'active')->pluck('membership_id')->map(fn ($id): int => (int) $id)->all();
            foreach ($membershipIds as $membershipId) {
                $access = PropertyMembership::query()->firstOrNew(['membership_id' => $membershipId, 'property_id' => $property->getKey()]);
                $access->status = $validSelected->contains($membershipId) ? 'active' : 'inactive';
                $access->save();
            }
            $after = $validSelected->map(fn ($id): int => (int) $id)->all();
            foreach (array_diff($after, $before) as $membershipId) {
                $this->audit($organization, $actor, 'property_access.granted', OrganizationMembership::query()->whereKey($membershipId)->value('user_id'), $property, ['property_id' => $property->getKey()]);
            }
            foreach (array_diff($before, $after) as $membershipId) {
                $this->audit($organization, $actor, 'property_access.revoked', OrganizationMembership::query()->whereKey($membershipId)->value('user_id'), $property, ['property_id' => $property->getKey()]);
            }
        });
    }

    public function audit(Organization $organization, User $actor, string $action, ?int $targetUserId = null, object|int|null $target = null, array $metadata = []): OrganizationAuditLog
    {
        $propertyId = $target instanceof Property ? $target->getKey() : $this->context->scopePropertyId();
        return OrganizationAuditLog::create([
            'organization_id' => $organization->getKey(),
            'property_id' => $propertyId,
            'actor_user_id' => $actor->getKey(),
            'target_user_id' => $targetUserId,
            'action' => $action,
            'target_type' => is_object($target) ? $target::class : null,
            'target_id' => is_object($target) ? $target->getKey() : (is_int($target) ? $target : null),
            'metadata' => $metadata ?: null,
        ]);
    }
}
