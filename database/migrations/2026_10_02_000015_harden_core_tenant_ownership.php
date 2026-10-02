<?php

use App\Services\OperationalTenantBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $propertyTables = [
        'departments', 'floors', 'room_categories', 'room_types', 'amenities', 'rooms',
        'reservations', 'room_blocks', 'payments', 'invoices', 'housekeeping_tasks',
        'maintenance_tasks',
    ];

    public function up(): void
    {
        if (DB::table('properties')->whereNotNull('organization_id')->exists()) {
            $report = app(OperationalTenantBackfillService::class)->run();
            if ($report['anomalies'] !== []) {
                throw new \RuntimeException('Cannot harden tenant ownership until the operational backfill is reconciled.');
            }
        }

        $this->assertNoNulls('properties', 'organization_id');
        foreach ($this->propertyTables as $table) {
            $this->assertNoNulls($table, 'property_id');
        }

        $this->harden('properties', 'organization_id', 'organizations');
        foreach ($this->propertyTables as $table) {
            $this->harden($table, 'property_id', 'properties');
        }
    }

    public function down(): void
    {
        $this->relax('properties', 'organization_id', 'organizations');
        foreach ($this->propertyTables as $table) {
            $this->relax($table, 'property_id', 'properties');
        }
    }

    private function assertNoNulls(string $table, string $column): void
    {
        $count = DB::table($table)->whereNull($column)->count();
        if ($count > 0) {
            throw new \RuntimeException("Cannot harden {$table}.{$column}: {$count} rows are still unassigned. Run the tenant backfill and reconcile ownership first.");
        }
    }

    private function harden(string $tableName, string $column, string $referencedTable): void
    {
        Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign([$column]));
        Schema::table($tableName, fn (Blueprint $table) => $table->unsignedBigInteger($column)->nullable(false)->change());
        Schema::table($tableName, fn (Blueprint $table) => $table->foreign($column)->references('id')->on($referencedTable)->restrictOnDelete());
    }

    private function relax(string $tableName, string $column, string $referencedTable): void
    {
        Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign([$column]));
        Schema::table($tableName, fn (Blueprint $table) => $table->unsignedBigInteger($column)->nullable()->change());
        Schema::table($tableName, fn (Blueprint $table) => $table->foreign($column)->references('id')->on($referencedTable)->nullOnDelete());
    }
};
