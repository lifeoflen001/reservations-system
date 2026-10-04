<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
            $table->dropUnique('tasks_task_number_unique');
            $table->unique(['property_id', 'task_number'], 'tasks_property_number_unique');
            $table->index(['property_id', 'status', 'due_at']);
            $table->index(['property_id', 'room_id']);
        });

        Schema::table('task_tags', function (Blueprint $table): void {
            $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
            $table->dropUnique('task_tags_name_unique');
            $table->unique(['property_id', 'name'], 'task_tags_property_name_unique');
        });

        Schema::table('announcements', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->after('organization_id')->constrained('properties')->nullOnDelete();
            $table->index(['organization_id', 'property_id', 'status', 'start_at']);
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->after('organization_id')->constrained('properties')->nullOnDelete();
            $table->index(['organization_id', 'property_id', 'read_at']);
        });

        Schema::table('channel_connections', function (Blueprint $table): void {
            $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
            $table->index(['property_id', 'provider', 'status']);
        });

        Schema::table('external_reservations', function (Blueprint $table): void {
            $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
            $table->index(['property_id', 'status', 'created_at']);
        });

        Schema::table('email_delivery_logs', function (Blueprint $table): void {
            $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
            $table->index(['property_id', 'status', 'created_at']);
        });

        Schema::table('gateway_transactions', function (Blueprint $table): void {
            $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
            $table->index(['property_id', 'status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::table('gateway_transactions', function (Blueprint $table): void {
            $table->dropIndex(['property_id', 'status', 'received_at']);
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
        });
        Schema::table('email_delivery_logs', function (Blueprint $table): void {
            $table->dropIndex(['property_id', 'status', 'created_at']);
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
        });
        Schema::table('external_reservations', function (Blueprint $table): void {
            $table->dropIndex(['property_id', 'status', 'created_at']);
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
        });
        Schema::table('channel_connections', function (Blueprint $table): void {
            $table->dropIndex(['property_id', 'provider', 'status']);
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
        });
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'property_id', 'read_at']);
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['property_id']);
            $table->dropColumn(['organization_id', 'property_id']);
        });
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'property_id', 'status', 'start_at']);
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['property_id']);
            $table->dropColumn(['organization_id', 'property_id']);
        });
        Schema::table('task_tags', function (Blueprint $table): void {
            $table->dropUnique('task_tags_property_name_unique');
            $table->unique('name');
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
        });
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropUnique('tasks_property_number_unique');
            $table->unique('task_number');
            $table->dropIndex(['property_id', 'status', 'due_at']);
            $table->dropIndex(['property_id', 'room_id']);
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
        });
    }
};
