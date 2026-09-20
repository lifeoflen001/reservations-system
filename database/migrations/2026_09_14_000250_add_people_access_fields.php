<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('alternate_phone')->nullable()->after('phone');
            $table->string('postal_code')->nullable()->after('city');
            $table->string('nationality', 3)->nullable()->after('country');
            $table->date('date_of_birth')->nullable()->after('nationality');
            $table->string('gender', 30)->nullable()->after('date_of_birth');
            $table->date('document_expiry')->nullable()->after('document_number');
            $table->boolean('is_active')->default(true)->after('notes');
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index('phone');
            $table->index('document_number');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->ipAddress('last_login_ip')->nullable()->after('last_login_at');
            $table->foreignId('created_by')->nullable()->after('last_login_ip')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->text('description')->nullable()->after('label');
            $table->boolean('is_system')->default(false)->after('description');
            $table->boolean('is_active')->default(true)->after('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) { $table->dropColumn(['description', 'is_system', 'is_active']); });
        Schema::table('departments', function (Blueprint $table) { $table->dropColumn(['description', 'sort_order']); });
        Schema::table('users', function (Blueprint $table) { $table->dropForeign(['created_by']); $table->dropForeign(['updated_by']); $table->dropColumn(['first_name', 'last_name', 'must_change_password', 'last_login_ip', 'created_by', 'updated_by']); });
        Schema::table('clients', function (Blueprint $table) { $table->dropForeign(['created_by']); $table->dropForeign(['updated_by']); $table->dropColumn(['middle_name', 'alternate_phone', 'postal_code', 'nationality', 'date_of_birth', 'gender', 'document_expiry', 'is_active', 'created_by', 'updated_by']); });
    }
};
