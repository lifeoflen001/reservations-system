<?php

namespace App\Services\Tenancy;

use App\Models\Organization;
use App\Models\OrganizationAuditLog;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrganizationOwnershipService
{
    /** @var array<int, string> */
    private const OWNER_MANAGEMENT_PERMISSIONS = [
        'organization.view', 'organization.update', 'members.view', 'members.manage',
        'properties.view', 'properties.create', 'properties.update', 'properties.manage_access',
        'audit.view',
    ];

    /** @return array<int, string> */
    public static function ownerManagementPermissions(): array
    {
        return self::OWNER_MANAGEMENT_PERMISSIONS;
    }

    public function __construct(private readonly TenantContext $context) {}

    public function isOwner(OrganizationMembership $membership): bool
    {
        return $membership->status === 'active' && (bool) $membership->is_owner;
    }

    public function isCurrentOrganizationOwner(User $actor): bool
    {
        $organizationId = $this->context->scopeOrganizationId();

        return $organizationId !== null && OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $actor->getKey())
            ->where('status', 'active')
            ->where('is_owner', true)
            ->exists();
    }

    public function canManageOwnership(User $actor): bool
    {
        return $this->isCurrentOrganizationOwner($actor);
    }

    public function assertOwner(User $actor): OrganizationMembership
    {
        $organization = $this->context->requireOrganization();
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $actor->getKey())
            ->where('status', 'active')
            ->where('is_owner', true)
            ->first();

        if (! $membership) {
            throw new AuthorizationException('Only an active organization owner can change ownership.');
        }

        return $membership;
    }

    public function grantOwner(OrganizationMembership $target, User $actor): OrganizationMembership
    {
        $organization = $this->assertActorOrganizationOwner($actor);
        $target = $this->assertTarget($target, $organization);

        if ($target->status !== 'active') {
            throw ValidationException::withMessages(['membership' => 'Only an active member can become an organization owner.']);
        }

        return DB::transaction(function () use ($target, $actor, $organization): OrganizationMembership {
            if (! $target->is_owner) {
                $target->forceFill(['is_owner' => true])->save();
                $this->audit($organization, $actor, 'organization.owner_granted', $target->user_id, $target, [
                    'ownership_change' => 'grant',
                ]);
            }

            return $target->fresh(['user', 'role', 'propertyAccess.property']);
        });
    }

    public function removeOwner(OrganizationMembership $target, User $actor): OrganizationMembership
    {
        $organization = $this->assertActorOrganizationOwner($actor);
        $target = $this->assertTarget($target, $organization);

        if (! $target->is_owner) {
            return $target;
        }

        return DB::transaction(function () use ($target, $actor, $organization): OrganizationMembership {
            $remainingOwners = OrganizationMembership::query()
                ->where('organization_id', $organization->getKey())
                ->where('status', 'active')
                ->where('is_owner', true)
                ->whereKeyNot($target->getKey())
                ->count();

            if ($remainingOwners < 1) {
                throw ValidationException::withMessages(['membership' => 'The organization must retain at least one active owner.']);
            }

            $target->forceFill(['is_owner' => false])->save();
            $this->audit($organization, $actor, 'organization.owner_removed', $target->user_id, $target, [
                'ownership_change' => 'remove',
            ]);

            return $target->fresh(['user', 'role', 'propertyAccess.property']);
        });
    }

    /** @return array{organizations_checked:int, owners_existing:int, owners_granted:int, selections:array<int,array<string,int|string>>} */
    public function backfill(bool $dryRun = false): array
    {
        $report = [
            'organizations_checked' => 0,
            'owners_existing' => 0,
            'owners_granted' => 0,
            'selections' => [],
        ];

        foreach (Organization::query()->orderBy('id')->get(['id']) as $organization) {
            $report['organizations_checked']++;
            $hasOwner = OrganizationMembership::query()
                ->where('organization_id', $organization->getKey())
                ->where('status', 'active')
                ->where('is_owner', true)
                ->exists();
            if ($hasOwner) {
                $report['owners_existing']++;
                continue;
            }

            $candidate = OrganizationMembership::query()
                ->with(['role.permissions'])
                ->where('organization_id', $organization->getKey())
                ->where('status', 'active')
                ->orderBy('id')
                ->get()
                ->sortByDesc(fn (OrganizationMembership $membership): int => $this->authorityScore($membership))
                ->first();

            if (! $candidate) {
                continue;
            }

            $roleName = (string) ($candidate->role?->name ?: 'none');
            $score = $this->authorityScore($candidate);
            $selection = [
                'organization_id' => (int) $organization->getKey(),
                'membership_id' => (int) $candidate->getKey(),
                'user_id' => (int) $candidate->user_id,
                'role' => $roleName,
                'authority_score' => $score,
                'selection' => 'highest-authority active membership; ties resolved by lowest membership id',
            ];
            $report['selections'][] = $selection;

            if ($dryRun) {
                $report['owners_granted']++;
                continue;
            }

            DB::transaction(function () use ($candidate, $organization, $roleName, $score, &$report): void {
                $candidate->forceFill(['is_owner' => true])->save();
                OrganizationAuditLog::create([
                    'organization_id' => $organization->getKey(),
                    'actor_user_id' => null,
                    'target_user_id' => $candidate->user_id,
                    'action' => 'organization.owner_granted',
                    'target_type' => OrganizationMembership::class,
                    'target_id' => $candidate->getKey(),
                    'metadata' => [
                        'ownership_change' => 'deterministic_backfill',
                        'selection' => 'highest-authority active membership; ties resolved by lowest membership id',
                        'role' => $roleName,
                        'authority_score' => $score,
                    ],
                ]);
                $report['owners_granted']++;
            });
        }

        return $report;
    }

    private function assertActorOrganizationOwner(User $actor): Organization
    {
        $this->assertOwner($actor);

        return $this->context->requireOrganization();
    }

    private function assertTarget(OrganizationMembership $target, Organization $organization): OrganizationMembership
    {
        if ((int) $target->organization_id !== (int) $organization->getKey()) {
            throw new AuthorizationException('The member does not belong to the current organization.');
        }

        return $target->load(['user', 'role', 'propertyAccess.property']);
    }

    private function authorityScore(OrganizationMembership $membership): int
    {
        return match ($membership->role?->name) {
            'super_administrator' => 400,
            'administrator' => 300,
            'manager' => 200,
            default => $membership->role?->permissions?->contains('name', 'members.manage') ? 150 : 0,
        };
    }

    private function audit(Organization $organization, User $actor, string $action, ?int $targetUserId, OrganizationMembership $target, array $metadata): void
    {
        OrganizationAuditLog::create([
            'organization_id' => $organization->getKey(),
            'actor_user_id' => $actor->getKey(),
            'target_user_id' => $targetUserId,
            'action' => $action,
            'target_type' => OrganizationMembership::class,
            'target_id' => $target->getKey(),
            'metadata' => $metadata,
        ]);
    }
}
