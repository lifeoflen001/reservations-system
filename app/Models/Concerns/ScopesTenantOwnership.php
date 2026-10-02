<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Property;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Applies the active tenant to normal Eloquent reads while retaining explicit
 * scopes for controllers, policies, jobs, and console tooling.
 *
 * The scope is intentionally inert when no tenant context exists. That keeps
 * migrations, backfills, seeders, and platform-global pages usable. Tenant
 * request middleware must resolve a context before operational controllers are
 * reached; explicit `forCurrentTenant()` throws if that contract is missing.
 */
trait ScopesTenantOwnership
{
    protected static function bootScopesTenantOwnership(): void
    {
        static::addGlobalScope('tenant-ownership', function (Builder $builder): void {
            $context = app(TenantContext::class);
            static::applyTenantScope($builder, $context);
        });
    }

    public function scopeForOrganization(Builder $query, Organization|int $organization): Builder
    {
        return $query->where($this->qualifyColumn('organization_id'), $organization instanceof Organization ? $organization->getKey() : $organization);
    }

    public function scopeForProperty(Builder $query, Property|int $property): Builder
    {
        return $query->where($this->qualifyColumn('property_id'), $property instanceof Property ? $property->getKey() : $property);
    }

    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $context = app(TenantContext::class);
        $model = $query->getModel();
        $table = $model->getTable();

        if (Schema::hasColumn($table, 'property_id')) {
            $propertyId = $context->propertyId();
            abort_unless($propertyId !== null, 403, 'An active property context is required.');

            return $query->where($model->qualifyColumn('property_id'), $propertyId);
        }

        if (Schema::hasColumn($table, 'organization_id')) {
            $organizationId = $context->organizationId();
            abort_unless($organizationId !== null, 403, 'An active organization context is required.');

            return $query->where($model->qualifyColumn('organization_id'), $organizationId);
        }

        return $query;
    }

    protected static function applyTenantScope(Builder $builder, TenantContext $context): void
    {
        $model = $builder->getModel();
        $table = $model->getTable();
        $organizationId = $context->scopeOrganizationId();
        $propertyId = $context->scopePropertyId();

        if (Schema::hasColumn($table, 'organization_id') && $organizationId !== null) {
            $builder->where($model->qualifyColumn('organization_id'), $organizationId);
        }

        if (! Schema::hasColumn($table, 'property_id') || $propertyId === null) {
            return;
        }

        // Announcements can be organization-wide. Property-specific records
        // remain restricted to the active property.
        if (in_array($table, ['announcements', 'notifications', 'webhook_inbound_events'], true)
            && Schema::hasColumn($table, 'organization_id')) {
            $builder->where(function (Builder $query) use ($model, $propertyId): void {
                $query->whereNull($model->qualifyColumn('property_id'))
                    ->orWhere($model->qualifyColumn('property_id'), $propertyId);
            });
            return;
        }

        $builder->where($model->qualifyColumn('property_id'), $propertyId);
    }
}
