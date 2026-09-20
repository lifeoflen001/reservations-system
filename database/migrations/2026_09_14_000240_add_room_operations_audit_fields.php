<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
        Schema::table('room_categories', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('description');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });
        Schema::table('room_types', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('base_rate');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });
        Schema::table('housekeeping_tasks', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
        });
        Schema::table('maintenance_tasks', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_tasks', function (Blueprint $table) { $table->dropForeign(['updated_by']); $table->dropForeign(['completed_by']); $table->dropColumn(['updated_by', 'completed_by']); });
        Schema::table('housekeeping_tasks', function (Blueprint $table) { $table->dropForeign(['updated_by']); $table->dropForeign(['completed_by']); $table->dropColumn(['updated_by', 'completed_by']); });
        Schema::table('room_types', function (Blueprint $table) { $table->dropColumn(['is_active', 'sort_order']); });
        Schema::table('room_categories', function (Blueprint $table) { $table->dropColumn(['is_active', 'sort_order']); });
        Schema::table('rooms', function (Blueprint $table) { $table->dropForeign(['created_by']); $table->dropForeign(['updated_by']); $table->dropColumn(['created_by', 'updated_by']); });
    }
};
