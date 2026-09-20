<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->index('created_at', 'reservations_created_at_analytics_index');
        });

        Schema::table('housekeeping_tasks', function (Blueprint $table): void {
            $table->index(['status', 'due_at'], 'housekeeping_status_due_analytics_index');
        });

        Schema::table('maintenance_tasks', function (Blueprint $table): void {
            $table->index(['status', 'due_at'], 'maintenance_status_due_analytics_index');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_tasks', function (Blueprint $table): void {
            $table->dropIndex('maintenance_status_due_analytics_index');
        });

        Schema::table('housekeeping_tasks', function (Blueprint $table): void {
            $table->dropIndex('housekeeping_status_due_analytics_index');
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex('reservations_created_at_analytics_index');
        });
    }
};
