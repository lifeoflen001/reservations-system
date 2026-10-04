<?php

namespace App\Services;

use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationAuditLog;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InvitationService
{
    public function create(User $actor, Organization $organization, string $email, int $roleId, array $propertyIds): OrganizationInvitation
    {
        $email = mb_strtolower(trim($email));
        $role = Role::query()->whereKey($roleId)->where('is_active', true)->first();
        if (! $role) throw ValidationException::withMessages(['role_id' => 'Select an active organization role.']);
        $properties = Property::query()->where('organization_id', $organization->getKey())->where('status', 'active')->whereIn('id', collect($propertyIds)->map(fn ($id) => (int) $id)->unique())->get();
        if ($properties->isEmpty()) throw ValidationException::withMessages(['property_ids' => 'Select at least one active property.']);
        if (OrganizationMembership::query()->where('organization_id', $organization->getKey())->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]))->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['email' => 'This person is already an active organization member.']);
        }
        $existing = OrganizationInvitation::query()->where('organization_id', $organization->getKey())->where('email', $email)->where('status', 'pending')->first();
        if ($existing) $this->revoke($existing, $actor, 'organization.invitation.replaced');

        $rawToken = Str::random(64);
        $invitation = DB::transaction(function () use ($actor, $organization, $email, $role, $properties, $rawToken): OrganizationInvitation {
            $invitation = OrganizationInvitation::create([
                'uuid' => (string) Str::uuid(), 'organization_id' => $organization->getKey(), 'role_id' => $role->getKey(),
                'email' => $email, 'status' => 'pending', 'token_hash' => hash('sha256', $rawToken),
                'expires_at' => now()->addDays((int) config('hotel.onboarding.invitation_days', 7)), 'invited_by' => $actor->getKey(),
            ]);
            $invitation->properties()->sync($properties->modelKeys());
            OrganizationAuditLog::create(['organization_id' => $organization->getKey(), 'actor_user_id' => $actor->getKey(), 'action' => 'organization.invitation.created', 'target_type' => OrganizationInvitation::class, 'target_id' => $invitation->getKey(), 'metadata' => ['email' => $email, 'role' => $role->name, 'property_count' => $properties->count()]]);
            return $invitation->load(['organization', 'role', 'properties']);
        });

        Mail::to($email)->send(new OrganizationInvitationMail($invitation, $rawToken));
        return $invitation;
    }

    public function accept(User $user, string $rawToken): OrganizationMembership
    {
        return DB::transaction(function () use ($user, $rawToken): OrganizationMembership {
            $invitation = OrganizationInvitation::query()->where('token_hash', hash('sha256', $rawToken))->lockForUpdate()->with(['organization', 'role', 'properties'])->first();
            if (! $invitation || ! $invitation->isUsable()) throw ValidationException::withMessages(['invitation' => 'This invitation is invalid, expired, revoked, or already used.']);
            if (mb_strtolower((string) $user->email) !== mb_strtolower($invitation->email)) throw ValidationException::withMessages(['invitation' => 'Sign in with the email address that received this invitation.']);
            $membership = OrganizationMembership::query()->where('organization_id', $invitation->organization_id)->where('user_id', $user->getKey())->first();
            if ($membership?->status === 'active') {
                $invitation->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();
                return $membership;
            }
            app(UsageLimitService::class)->assertCanConsume($invitation->organization, 'users');
            $membership ??= new OrganizationMembership(['organization_id' => $invitation->organization_id, 'user_id' => $user->getKey()]);
            $membership->forceFill(['role_id' => $invitation->role_id, 'status' => 'active', 'is_owner' => false, 'joined_at' => now(), 'invited_by' => $invitation->invited_by])->save();
            foreach ($invitation->properties as $property) PropertyMembership::updateOrCreate(['membership_id' => $membership->getKey(), 'property_id' => $property->getKey()], ['access_level' => 'standard', 'status' => 'active']);
            $invitation->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();
            OrganizationAuditLog::create(['organization_id' => $invitation->organization_id, 'actor_user_id' => $user->getKey(), 'target_user_id' => $user->getKey(), 'action' => 'organization.invitation.accepted', 'target_type' => OrganizationMembership::class, 'target_id' => $membership->getKey()]);
            return $membership->fresh(['organization', 'propertyAccess.property']);
        });
    }

    public function revoke(OrganizationInvitation $invitation, User $actor, string $action = 'organization.invitation.revoked'): void
    {
        if ($invitation->status !== 'pending') return;
        $invitation->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();
        OrganizationAuditLog::create(['organization_id' => $invitation->organization_id, 'actor_user_id' => $actor->getKey(), 'action' => $action, 'target_type' => OrganizationInvitation::class, 'target_id' => $invitation->getKey(), 'metadata' => ['email' => $invitation->email]]);
    }

    public function resend(OrganizationInvitation $old, User $actor): OrganizationInvitation
    {
        $old->load(['organization', 'role', 'properties']);
        $propertyIds = $old->properties->modelKeys();
        $this->revoke($old, $actor, 'organization.invitation.resent');
        return $this->create($actor, $old->organization, $old->email, (int) $old->role_id, $propertyIds);
    }
}
