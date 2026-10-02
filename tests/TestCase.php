<?php

namespace Tests;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TestingTenantBootstrap;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function actingAs($user, $guard = null): static
    {
        $result = parent::actingAs($user, $guard);

        if (app()->runningUnitTests() && $user instanceof User) {
            $organization = Organization::query()->where('slug', 'automated-test-organization')->first();
            if (! $organization) {
                $property = app(TestingTenantBootstrap::class)->activate();
                $organization = $property->organization;
            }
            $property = $organization->properties()->orderBy('id')->first();
            if (! $property instanceof Property) {
                $property = app(TestingTenantBootstrap::class)->activate();
                $organization = $property->organization;
            }
            $membership = OrganizationMembership::query()->firstOrCreate(
                ['organization_id' => $organization->getKey(), 'user_id' => $user->getKey()],
                ['status' => 'active', 'role_id' => $user->role_id, 'joined_at' => now()],
            );
            PropertyMembership::query()->firstOrCreate(
                ['membership_id' => $membership->getKey(), 'property_id' => $property->getKey()],
                ['status' => 'active'],
            );
            app(TenantContext::class)->activate($organization->getKey(), $property->getKey());
        }

        return $result;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // The application container is reused by the test runner. Clear any
        // execution context left by the previous test before RefreshDatabase
        // creates its next isolated schema.
        app(TenantContext::class)->release();

        $mysqlIntegration = app()->environment('testing')
            && filter_var(env('POS_MYSQL_INTEGRATION', false), FILTER_VALIDATE_BOOL)
            && config('database.default') === 'mysql'
            && str_starts_with((string) config('database.connections.mysql.database'), 'reservations_pos_integration_')
            && ! str_contains((string) config('database.connections.mysql.database'), 'reservations_db');
        $sqlite = app()->environment('testing')
            && config('database.default') === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:';

        if (! $sqlite && ! $mysqlIntegration) {
            throw new RuntimeException('Automated tests must use APP_ENV=testing with the isolated SQLite :memory: database.');
        }
    }
}
