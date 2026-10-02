<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'clients', 'announcements', 'notifications', 'integration_settings', 'webhook_endpoints',
        'api_tokens', 'integration_logs', 'webhook_deliveries',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $count = DB::table($table)->whereNull('organization_id')->count();
            if ($count > 0) {
                throw new \RuntimeException("Cannot harden {$table}.organization_id: {$count} rows are still unassigned. Run the tenant backfill and reconcile ownership first.");
            }
        }
        foreach ($this->tables as $table) {
            $this->harden($table);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign(['organization_id']));
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('organization_id')->nullable()->change());
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete());
        }
    }

    private function harden(string $tableName): void
    {
        Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign(['organization_id']));
        Schema::table($tableName, fn (Blueprint $table) => $table->unsignedBigInteger('organization_id')->nullable(false)->change());
        Schema::table($tableName, fn (Blueprint $table) => $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete());
    }
};
