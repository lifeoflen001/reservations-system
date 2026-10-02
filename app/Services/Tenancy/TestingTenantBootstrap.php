<?php

namespace App\Services\Tenancy;

use App\Models\Organization;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Supplies an isolated tenant only for legacy unit/feature fixtures that
 * create an operational model directly without exercising installation.
 * Production requests never call this class; they must resolve a trusted
 * organization and property before creating tenant-owned data.
 */
final class TestingTenantBootstrap
{
    public function activate(): Property
    {
        $organization = Organization::query()->first();
        if (! $organization) {
            $organization = Organization::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Automated Test Organization',
                'slug' => 'automated-test-organization',
                'status' => 'active',
            ]);
        }

        $property = Property::query()->where('organization_id', $organization->getKey())->orderBy('id')->first();
        if (! $property) {
            $propertyId = DB::table('properties')->insertGetId([
                'organization_id' => $organization->getKey(),
                'name' => 'Automated Test Property',
                'uuid' => (string) Str::uuid(),
                'slug' => 'automated-test-property',
                'property_code' => 'TEST-001',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $property = Property::query()->findOrFail($propertyId);
        }

        app(TenantContext::class)->activate((int) $organization->getKey(), (int) $property->getKey());

        return $property;
    }
}
