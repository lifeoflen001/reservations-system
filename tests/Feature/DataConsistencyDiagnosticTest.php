<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use App\Models\Department;
use App\Models\Role;
use App\Models\Currency;
use App\Models\PaymentMethod;
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

    public function test_database_seeders_preserve_existing_operational_values(): void
    {
        $this->seed(DatabaseSeeder::class);

        $currency = Currency::where('code', 'USD')->firstOrFail();
        $currency->update(['name' => 'Property-configured US Dollar']);
        $paymentMethod = PaymentMethod::where('code', 'cash')->firstOrFail();
        $paymentMethod->update(['name' => 'Cash drawer']);
        $role = Role::where('name', 'administrator')->firstOrFail();
        $role->update(['label' => 'Property Administrator']);
        $admin = User::where('username', 'admin')->firstOrFail();
        $admin->update(['name' => 'Existing Property Administrator']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Property-configured US Dollar', $currency->fresh()->name);
        $this->assertSame('Cash drawer', $paymentMethod->fresh()->name);
        $this->assertSame('Property Administrator', $role->fresh()->label);
        $this->assertSame('Existing Property Administrator', $admin->fresh()->name);
    }

    public function test_default_staff_catalog_stays_compact_without_removing_legacy_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['Management', 'Front Office', 'Housekeeping', 'Maintenance', 'Finance'],
            Department::where('is_active', true)->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['Super Administrator', 'Administrator', 'Manager', 'Front Office', 'Operations', 'Finance / Accounts', 'Accountant', 'Finance Manager'],
            Role::where('is_active', true)->pluck('label')->all(),
        );
        $this->assertDatabaseMissing('departments', ['name' => 'Administration', 'is_active' => true]);
        $this->assertDatabaseMissing('roles', ['name' => 'maintenance', 'is_active' => true]);
        $this->assertDatabaseMissing('roles', ['name' => 'cashier', 'is_active' => true]);
        $this->assertTrue(Role::where('name', 'front_desk')->firstOrFail()->permissions()->where('name', 'pos.sell')->exists());
        $this->assertTrue(Role::where('name', 'housekeeper')->firstOrFail()->permissions()->where('name', 'maintenance.update')->exists());
    }
}
