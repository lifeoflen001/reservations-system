<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Central ownership assertions shared by tenant-aware services and policies.
 *
 * Query scopes provide the default read boundary; these checks protect
 * explicit cross-record references and authorization decisions.
 */
final class TenantOwnershipConsistencyService
{
    public function __construct(private readonly TenantContext $context) {}

    public function currentPropertyId(): ?int
    {
        return $this->context->propertyId();
    }

    public function currentOrganizationId(): ?int
    {
        return $this->context->organizationId();
    }

    public function assertCurrentOrganization(?int $organizationId, string $message = 'The record does not belong to the active organization.'): void
    {
        $currentOrganizationId = $this->currentOrganizationId();

        if ($currentOrganizationId !== null && $organizationId !== null && $currentOrganizationId !== $organizationId) {
            throw new InvalidArgumentException($message);
        }
    }

    public function assertCurrentProperty(?int $propertyId, string $message = 'The record does not belong to the active property.'): void
    {
        $currentPropertyId = $this->currentPropertyId();

        if ($currentPropertyId !== null && $propertyId !== null && $currentPropertyId !== $propertyId) {
            throw new InvalidArgumentException($message);
        }
    }

    public function assertSameProperty(?int $left, ?int $right, string $message = 'Related records must belong to the same property.'): void
    {
        if ($left !== null && $right !== null && $left !== $right) {
            throw new InvalidArgumentException($message);
        }
    }

    public function assertPropertyExists(int $propertyId, string $message = 'The selected property is not available.'): Property
    {
        $property = Property::query()->whereKey($propertyId)->first();

        if (! $property) {
            throw new InvalidArgumentException($message);
        }

        $this->assertCurrentProperty($property->getKey(), $message);

        return $property;
    }

    public function owns(Model $model): bool
    {
        $propertyId = $this->currentPropertyId();
        $organizationId = $this->currentOrganizationId();

        // Preserve legacy/setup fixtures before a tenant exists. Protected
        // production requests are required to resolve a context by middleware.
        if ($propertyId === null && $organizationId === null) {
            return true;
        }

        if ($model instanceof User) {
            // Users without an operational department are installation-level
            // identities (for example the bootstrap administrator). Once a
            // staff member is assigned to a department, their ownership is
            // enforced through that department's property.
            return $model->department?->property_id === null
                || ($propertyId !== null && (int) $model->department->property_id === $propertyId);
        }

        if ($model->getAttribute('organization_id') !== null && (int) $model->getAttribute('organization_id') !== (int) $organizationId) {
            return false;
        }

        if ($model->getAttribute('property_id') !== null) {
            return $propertyId !== null && (int) $model->getAttribute('property_id') === $propertyId;
        }

        return $organizationId !== null && $model->getAttribute('organization_id') !== null;
    }
}
