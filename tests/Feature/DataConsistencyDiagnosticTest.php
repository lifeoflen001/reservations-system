<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataConsistencyDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_data_diagnostic_reports_the_runtime_connection_and_healthy_empty_database(): void
    {
        $this->artisan('pms:diagnose-data')
            ->expectsOutputToContain('PMS data consistency diagnostic (read-only)')
            ->expectsOutputToContain('Foreign-key orphans: 0')
            ->expectsOutputToContain('Invalid status values: 0')
            ->expectsOutputToContain('Pending migrations: none')
            ->assertExitCode(0);
    }

    public function test_database_seeder_is_idempotent_when_run_against_existing_records(): void
    {
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--no-interaction' => true])->assertExitCode(0);
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--no-interaction' => true])->assertExitCode(0);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('room_categories', 3);
        $this->assertDatabaseCount('room_types', 3);
        $this->assertDatabaseCount('rooms', 5);

        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $superAdministrator = Role::query()
            ->with('permissions')
            ->where('name', 'super_administrator')
            ->firstOrFail();

        $this->assertSame($superAdministrator->getKey(), $admin->role_id);
        $this->assertEqualsCanonicalizing(
            config('hotel.permissions', []),
            $superAdministrator->permissions->pluck('name')->all(),
        );
    }
}
