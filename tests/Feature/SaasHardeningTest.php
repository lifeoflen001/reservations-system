<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\FinancialAccount;
use App\Models\Property;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class SaasHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_mandatory_ownership_columns_are_not_nullable(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ([
            'departments', 'floors', 'room_categories', 'room_types', 'amenities', 'rooms',
            'reservations', 'room_blocks', 'payments', 'invoices', 'housekeeping_tasks',
            'maintenance_tasks', 'financial_accounts', 'expense_categories',
            'financial_transactions', 'expenses', 'fund_transfers', 'finance_reconciliations',
            'daily_cash_closes', 'payment_refunds', 'pos_outlets', 'pos_categories',
            'pos_products', 'pos_shifts', 'pos_orders', 'pos_room_charges', 'pos_audits',
            'tasks', 'task_tags', 'channel_connections', 'external_reservations',
        ] as $table) {
            $column = collect(Schema::getColumns($table))->firstWhere('name', 'property_id');

            $this->assertNotNull($column, $table.' must have property_id.');
            $this->assertFalse((bool) $column['nullable'], $table.' must require property ownership.');
        }

        foreach ([
            'clients', 'announcements', 'notifications', 'integration_settings', 'webhook_endpoints',
            'api_tokens', 'integration_logs', 'webhook_deliveries', 'webhook_inbound_events',
        ] as $table) {
            $column = collect(Schema::getColumns($table))->firstWhere('name', 'organization_id');

            $this->assertNotNull($column, $table.' must have organization_id.');
            $this->assertFalse((bool) $column['nullable'], $table.' must require organization ownership.');
        }
    }

    public function test_tenant_owned_creation_requires_context_and_cannot_be_reassigned(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        app(TenantContext::class)->release();
        $this->expectException(LogicException::class);
        FinancialAccount::create([
            'name' => 'No context account', 'code' => 'no_context_account', 'type' => 'cash',
            'currency' => 'USD', 'is_active' => true,
        ]);
    }

    public function test_active_context_assigns_ownership_and_rejects_explicit_tampering(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $this->actingAs($admin);

        $property = Property::query()->firstOrFail();
        $account = FinancialAccount::create([
            'name' => 'Context account', 'code' => 'context_account', 'type' => 'cash',
            'currency' => 'USD', 'is_active' => true,
        ]);

        $this->assertSame($property->getKey(), $account->property_id);

        $otherProperty = Property::create([
            'organization_id' => $property->organization_id,
            'name' => 'Other property',
            'uuid' => fake()->uuid(),
            'slug' => 'other-property-'.fake()->unique()->numberBetween(10, 99),
            'property_code' => 'OTHER-'.fake()->unique()->numberBetween(10, 99),
            'status' => 'active',
        ]);

        $tampered = FinancialAccount::create([
            'property_id' => $otherProperty->getKey(),
            'name' => 'Tampered account', 'code' => 'tampered_account', 'type' => 'cash',
            'currency' => 'USD', 'is_active' => true,
        ]);

        $this->assertSame($property->getKey(), $tampered->property_id);
        $this->assertNotSame($otherProperty->getKey(), $tampered->property_id);
    }

    public function test_organization_owned_records_keep_explicitly_nullable_property_scope(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $this->actingAs($admin);

        $announcement = Announcement::create([
            'title' => 'Organization-wide notice',
            'short_description' => 'This notice is intentionally organization-wide.',
            'content' => 'This notice is intentionally organization-wide.',
            'start_at' => now(),
            'end_at' => now()->addDay(),
            'status' => 'draft',
            'is_active' => false,
        ]);

        $this->assertSame(app(TenantContext::class)->organizationId(), $announcement->organization_id);
        $this->assertNull($announcement->property_id);
    }
}
