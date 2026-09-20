<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->foreignId('language_id')->nullable()->after('phone')->constrained('languages')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('language_id')->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('role_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['language_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['role_id']);
            $table->dropColumn([
                'username', 'phone', 'language_id', 'department_id', 'role_id',
                'is_active', 'last_login_at',
            ]);
        });
    }
};
