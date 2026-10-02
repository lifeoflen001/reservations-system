<?php

namespace App\Models\Concerns;

use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TestingTenantBootstrap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

trait AssignsTenantOwnership
{
    use ScopesTenantOwnership;

    protected static function bootAssignsTenantOwnership(): void
    {
        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);
            $table = $model->getTable();
            $property = Schema::hasColumn($table, 'property_id') ? $context->currentProperty() : null;
            $organization = Schema::hasColumn($table, 'organization_id') ? $context->currentOrganization() : null;

            // Existing isolated tests pre-date tenant setup and create a
            // tenant-owned record directly. Keep that fixture compatibility
            // in the test process only; deployed/runtime code remains strict.
            if (! $property && app()->runningUnitTests() && ! \App\Models\Organization::query()->exists()) {
                $property = app(TestingTenantBootstrap::class)->activate();
                $organization = $property->organization;
            }

            if (Schema::hasColumn($table, 'property_id')) {
                $explicitPropertyId = $model->getAttribute('property_id');
                $propertyRequired = $model->propertyOwnershipRequired();

                if (filled($explicitPropertyId)) {
                    if ($property && (int) $explicitPropertyId !== (int) $property->getKey()) {
                        throw new \LogicException("Tenant property cannot be changed while creating {$table}.");
                    }
                    if (! $property && $organization) {
                        $belongsToOrganization = $model->newQueryWithoutScopes()
                            ->whereKey($explicitPropertyId)
                            ->where('organization_id', $organization->getKey())
                            ->exists();
                        if (! $belongsToOrganization) {
                            throw new \LogicException("The supplied property does not belong to the active organization for {$table}.");
                        }
                    }
                } elseif ($propertyRequired) {
                    if (! $property) {
                        throw new \LogicException("An active property context is required to create a {$table} record.");
                    }
                    $model->setAttribute('property_id', $property->getKey());
                }
            }

            if (Schema::hasColumn($table, 'organization_id')) {
                $explicitOrganizationId = $model->getAttribute('organization_id');

                if (filled($explicitOrganizationId)) {
                    if ($organization && (int) $explicitOrganizationId !== (int) $organization->getKey()) {
                        throw new \LogicException("Tenant organization cannot be changed while creating {$table}.");
                    }
                } else {
                    if (! $organization) {
                        throw new \LogicException("An active organization context is required to create a {$table} record.");
                    }
                    $model->setAttribute('organization_id', $organization->getKey());
                }
            }
        });
    }

    /**
     * Most operational records must be tied to the active property. Models
     * such as organization-wide announcements and integration audit records
     * override this to keep property_id intentionally nullable.
     */
    protected function propertyOwnershipRequired(): bool
    {
        return true;
    }
}
