<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('reservations', ['check_in', 'check_out'], 'reservations_check_in_check_out_index');
        $this->addIndex('rooms', ['is_active', 'room_category_id', 'room_type_id', 'floor_id'], 'rooms_planning_filters_index');
        $this->addIndex('room_blocks', ['is_active', 'starts_at', 'ends_at', 'room_id'], 'room_blocks_window_index');
        $this->addIndex('maintenance_tasks', ['status', 'starts_at', 'ends_at', 'room_id'], 'maintenance_window_index');
        $this->addIndex('tasks', ['archived_at', 'status', 'due_at'], 'tasks_active_status_due_index');
    }

    public function down(): void
    {
        foreach ([
            ['tasks', 'tasks_active_status_due_index'],
            ['maintenance_tasks', 'maintenance_window_index'],
            ['room_blocks', 'room_blocks_window_index'],
            ['rooms', 'rooms_planning_filters_index'],
            ['reservations', 'reservations_check_in_check_out_index'],
        ] as [$table, $index]) {
            $this->dropIndex($table, $index);
        }
    }

    private function addIndex(string $tableName, array $columns, string $indexName): void
    {
        if (collect(Schema::getIndexes($tableName))->contains('name', $indexName)) return;
        Schema::table($tableName, fn (Blueprint $table) => $table->index($columns, $indexName));
    }

    private function dropIndex(string $tableName, string $indexName): void
    {
        if (! collect(Schema::getIndexes($tableName))->contains('name', $indexName)) return;
        Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($indexName));
    }
};
