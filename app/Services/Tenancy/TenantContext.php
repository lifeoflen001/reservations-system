<?php

namespace App\Services\Tenancy;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class TenantContext
{
    public const ORGANIZATION_SESSION_KEY = 'tenant.organization_id';

    public const PROPERTY_SESSION_KEY = 'tenant.property_id';

    private ?Organization $organization = null;

    private ?OrganizationMembership $membership = null;

    private ?Property $property = null;

    private bool $resolved = false;

    /**
     * A released context must stay empty until the next trusted request/job
     * explicitly resolves it. This prevents lazy model reads after a request
     * from silently reactivating the browser's previous tenant.
     */
    private bool $resolutionDisabled = false;

    private ?int $resolvedUserId = null;

    private ?Request $resolvedRequest = null;

    public function currentOrganization(): ?Organization
    {
        $this->ensureResolved();

        return $this->organization;
    }

    public function currentProperty(): ?Property
    {
        $this->ensureResolved();

        return $this->property;
    }

    public function organizationId(): ?int
    {
        return $this->currentOrganization()?->getKey();
    }

    public function propertyId(): ?int
    {
        return $this->currentProperty()?->getKey();
    }

    public function requireOrganization(): Organization
    {
        $organization = $this->currentOrganization();
        if (! $organization) {
            throw new AuthorizationException('An active organization context is required.');
        }

        return $organization;
    }

    public function requireProperty(): Property
    {
        $property = $this->currentProperty();
        if (! $property) {
            throw new AuthorizationException('An active property context is required.');
        }

        return $property;
    }

    /** Return the already-resolved organization without lazy resolution. */
    public function scopeOrganizationId(): ?int
    {
        return $this->organization?->getKey();
    }

    /** Return the already-resolved property without lazy resolution. */
    public function scopePropertyId(): ?int
    {
        return $this->property?->getKey();
    }

    public function hasOrganization(): bool
    {
        return $this->currentOrganization() !== null;
    }

    public function hasProperty(): bool
    {
        return $this->currentProperty() !== null;
    }

    /**
     * Resolve and remember the current valid context for one request.
     *
     * Session IDs are only preferences. Every resolution re-queries active
     * memberships and property access so revocations take effect immediately.
     */
    public function resolveFor(User $user, ?int $organizationId = null, ?int $propertyId = null): void
    {
        $this->resolutionDisabled = false;
        $request = $this->requestInstance();
        if ($organizationId === null && $propertyId === null
            && $this->resolved
            && $this->resolvedUserId === $user->getKey()
            && ($request === null || $this->resolvedRequest === $request)) {
            return;
        }

        $this->resetState();
        $this->resolved = true;
        $this->resolvedUserId = $user->getKey();
        $this->resolvedRequest = $request;

        if ($user->is_active === false) {
            $this->forgetSessionState();

            return;
        }

        $memberships = OrganizationMembership::query()
            ->with([
                'organization',
                'propertyAccess' => fn ($query) => $query->with('property'),
            ])
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->whereHas('organization', fn ($query) => $query->where('status', 'active'))
            ->orderBy('organization_id')
            ->get();

        $requestedOrganizationId = $organizationId ?? $this->session()?->get(self::ORGANIZATION_SESSION_KEY);
        $membership = $memberships->first(fn (OrganizationMembership $candidate): bool => (int) $candidate->organization_id === (int) $requestedOrganizationId);
        $membership ??= $memberships->first();

        if (! $membership) {
            $this->forgetSessionState();

            return;
        }

        $this->membership = $membership;
        $this->organization = $membership->organization;
        $this->rememberOrganization();

        $requestedPropertyId = $propertyId ?? $this->session()?->get(self::PROPERTY_SESSION_KEY);
        $this->selectProperty($membership, $requestedPropertyId);
    }

    /** Establish a trusted context for a queued job or an authenticated API token. */
    public function activate(int $organizationId, ?int $propertyId = null): void
    {
        $this->resolutionDisabled = false;
        $organization = Organization::query()->whereKey($organizationId)->where('status', 'active')->firstOrFail();
        $property = null;
        if ($propertyId !== null) {
            $property = Property::query()->whereKey($propertyId)->where('organization_id', $organization->getKey())->where('status', 'active')->firstOrFail();
        }

        $this->resetState();
        $this->resolved = true;
        $this->organization = $organization;
        $this->property = $property;
    }

    /** Clear execution-only context without mutating a browser session. */
    public function release(): void
    {
        $this->resetState();
        $this->resolutionDisabled = true;
    }

    public function setOrganization(Organization $organization): void
    {
        $this->resolutionDisabled = false;
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new AuthorizationException('An authenticated user is required to select an organization.');
        }

        $membership = OrganizationMembership::query()
            ->with(['organization', 'propertyAccess.property'])
            ->where('user_id', $user->getKey())
            ->where('organization_id', $organization->getKey())
            ->where('status', 'active')
            ->whereHas('organization', fn ($query) => $query->where('status', 'active'))
            ->first();

        if (! $membership) {
            throw new AuthorizationException('You do not have access to this organization.');
        }

        $this->resolved = true;
        $this->resolvedUserId = $user->getKey();
        $this->resolvedRequest = $this->requestInstance();
        $this->membership = $membership;
        $this->organization = $membership->organization;
        $this->property = null;
        $this->rememberOrganization();
        $this->forgetProperty();
        $this->selectProperty($membership);
    }

    public function setProperty(Property $property): void
    {
        $this->ensureResolved();

        if (! $this->membership || ! $this->organization) {
            throw new AuthorizationException('An active organization is required to select a property.');
        }

        $access = PropertyMembership::query()
            ->with('property')
            ->where('membership_id', $this->membership->getKey())
            ->where('property_id', $property->getKey())
            ->where('status', 'active')
            ->whereHas('property', function ($query): void {
                $query->where('organization_id', $this->organization->getKey())->where('status', 'active');
            })
            ->first();

        if (! $access) {
            throw new AuthorizationException('You do not have access to this property.');
        }

        $this->property = $access->property;
        $this->rememberProperty();
    }

    public function clear(): void
    {
        $this->forgetSessionState();
        $this->resetState();
        $this->resolutionDisabled = true;
    }

    public function membership(): ?OrganizationMembership
    {
        $this->ensureResolved();

        return $this->membership;
    }

    /** @return Collection<int, Property> */
    public function accessibleProperties(): Collection
    {
        $this->ensureResolved();

        if (! $this->membership) {
            return collect();
        }

        return $this->activePropertyAccess($this->membership)
            ->map(fn (PropertyMembership $access): ?Property => $access->property)
            ->filter()
            ->sortBy('id')
            ->values();
    }

    /** @return Collection<int, Organization> */
    public function accessibleOrganizations(): Collection
    {
        return $this->accessibleOrganizationMemberships()
            ->map(fn (OrganizationMembership $membership): ?Organization => $membership->organization)
            ->filter()
            ->unique('id')
            ->values();
    }

    /** @return Collection<int, OrganizationMembership> */
    public function accessibleOrganizationMemberships(): Collection
    {
        $this->ensureResolved();
        $user = Auth::user();
        if (! $user instanceof User) {
            return collect();
        }

        return OrganizationMembership::query()
            ->with(['organization' => fn ($query) => $query->with(['properties' => fn ($properties) => $properties->where('status', 'active')->orderBy('name')])])
            ->with(['propertyAccess' => fn ($query) => $query->where('status', 'active')->with(['property' => fn ($property) => $property->where('status', 'active')])])
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->whereHas('organization', fn ($query) => $query->where('status', 'active'))
            ->orderBy('organization_id')
            ->get();
    }

    /**
     * Remove transient selections that must never survive a tenant switch.
     * The session remains the source of the active tenant preference only.
     */
    public function clearTenantSensitiveSessionState(): void
    {
        $this->session()?->forget([
            'selected_room', 'reservation.filters', 'reservations.filters',
            'pos.outlet_id', 'pos.cart', 'pos.shift_id', 'pos.active_shift_id',
            'finance.account_id', 'finance.reconciliation', 'finance.filters',
            'reports.property_id', 'reports.filters', 'tasks.filters',
            'calendar.selection', 'search.query', 'search.results',
            'pagination', 'recent_ids',
        ]);
    }

    private function ensureResolved(): void
    {
        if ($this->resolved) {
            return;
        }

        if ($this->resolutionDisabled) {
            $this->resolutionDisabled = false;
            $user = Auth::user();
            if ($user instanceof User) {
                $this->resolveFor($user);
            } else {
                $this->resolved = true;
            }

            return;
        }

        $user = Auth::user();
        if ($user instanceof User) {
            $this->resolveFor($user);
            return;
        }

        $this->resolved = true;
    }

    private function selectProperty(OrganizationMembership $membership, mixed $requestedPropertyId = null): void
    {
        $accesses = $this->activePropertyAccess($membership);
        $propertyAccess = $accesses->first(fn (PropertyMembership $access): bool => (int) $access->property_id === (int) $requestedPropertyId);
        $propertyAccess ??= $accesses->sortBy('property_id')->first();

        if (! $propertyAccess || ! $propertyAccess->property) {
            $this->property = null;
            $this->forgetProperty();

            return;
        }

        $this->property = $propertyAccess->property;
        $this->rememberProperty();
    }

    /** @return Collection<int, PropertyMembership> */
    private function activePropertyAccess(OrganizationMembership $membership): Collection
    {
        return $membership->propertyAccess
            ->filter(function (PropertyMembership $access) use ($membership): bool {
                return $access->status === 'active'
                    && $access->property?->status === 'active'
                    && (int) $access->property?->organization_id === (int) $membership->organization_id;
            })
            ->values();
    }

    private function rememberOrganization(): void
    {
        $this->session()?->put(self::ORGANIZATION_SESSION_KEY, $this->organization?->getKey());
    }

    private function rememberProperty(): void
    {
        if ($this->property) {
            $this->session()?->put(self::PROPERTY_SESSION_KEY, $this->property->getKey());
        }
    }

    private function forgetProperty(): void
    {
        $this->session()?->forget(self::PROPERTY_SESSION_KEY);
    }

    private function forgetSessionState(): void
    {
        $this->session()?->forget([self::ORGANIZATION_SESSION_KEY, self::PROPERTY_SESSION_KEY]);
    }

    private function resetState(): void
    {
        $this->organization = null;
        $this->membership = null;
        $this->property = null;
        $this->resolved = false;
        $this->resolvedUserId = null;
        $this->resolvedRequest = null;
    }

    private function session(): ?\Illuminate\Session\Store
    {
        $request = $this->requestInstance();

        return $request?->hasSession() ? $request->session() : null;
    }

    private function requestInstance(): ?Request
    {
        return app()->bound('request') ? app(Request::class) : null;
    }
}
