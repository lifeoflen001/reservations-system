<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['pos_outlets', 'pos_categories', 'pos_products', 'pos_shifts', 'pos_orders', 'pos_room_charges', 'pos_audits'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $count = DB::table($table)->whereNull('property_id')->count();
            if ($count > 0) {
                throw new \RuntimeException("Cannot harden {$table}.property_id: {$count} rows are still unassigned. Run the tenant backfill and reconcile ownership first.");
            }
        }
        foreach ($this->tables as $table) {
            $this->harden($table);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign(['property_id']));
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('property_id')->nullable()->change());
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->foreign('property_id')->references('id')->on('properties')->nullOnDelete());
        }
    }

    private function harden(string $tableName): void
    {
        Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign(['property_id']));
        Schema::table($tableName, fn (Blueprint $table) => $table->unsignedBigInteger('property_id')->nullable(false)->change());
        Schema::table($tableName, fn (Blueprint $table) => $table->foreign('property_id')->references('id')->on('properties')->restrictOnDelete());
    }
};
