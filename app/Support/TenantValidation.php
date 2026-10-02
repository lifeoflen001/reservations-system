<?php

namespace App\Support;

use App\Services\Tenancy\TenantContext;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Rule;

final class TenantValidation
{
    public static function propertyExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where(fn ($query) => $query->where('property_id', app(TenantContext::class)->propertyId()));
    }

    public static function organizationExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where(fn ($query) => $query->where('organization_id', app(TenantContext::class)->organizationId()));
    }

    public static function propertyUnique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)
            ->where(fn ($query) => $query->where('property_id', app(TenantContext::class)->propertyId()));
    }

    private function __construct() {}
}
