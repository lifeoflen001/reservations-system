<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('users', ['last_login_at'], 'users_last_login_at_index');
        $this->addIndex('housekeeping_tasks', ['status', 'completed_at'], 'housekeeping_status_completed_at_index');
        $this->addIndex('maintenance_tasks', ['status', 'priority', 'due_at'], 'maintenance_status_priority_due_at_index');
        $this->addIndex('payments', ['status', 'transaction_date'], 'payments_status_transaction_date_index');
        $this->addIndex('clients', ['created_at'], 'clients_created_at_index');
    }

    public function down(): void
    {
        $this->dropIndex('clients', 'clients_created_at_index');
        $this->dropIndex('payments', 'payments_status_transaction_date_index');
        $this->dropIndex('maintenance_tasks', 'maintenance_status_priority_due_at_index');
        $this->dropIndex('housekeeping_tasks', 'housekeeping_status_completed_at_index');
        $this->dropIndex('users', 'users_last_login_at_index');
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
