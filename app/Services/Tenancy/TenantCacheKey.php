<?php

namespace App\Services\Tenancy;

use App\Models\Property;

final class TenantCacheKey
{
    public static function make(string $namespace, ?Property $property = null): string
    {
        $context = app(TenantContext::class);
        $organization = $context->currentOrganization();
        $property ??= $context->currentProperty();

        $organizationKey = $organization?->uuid ?: ($organization?->getKey() ? 'org-'.$organization->getKey() : 'platform');
        $propertyKey = $property?->uuid ?: ($property?->getKey() ? 'property-'.$property->getKey() : 'organization');

        return implode(':', [$namespace, $organizationKey, $propertyKey]);
    }
}
