<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_administrators', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->string('role', 64)->default('platform_admin')->index();
            $table->json('permissions')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('platform_support_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('platform_administrator_id')->constrained('platform_administrators')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 24)->default('active')->index();
            // Use DATETIME for multiple required lifecycle fields. MariaDB
            // 10.4 applies legacy implicit defaults to non-null TIMESTAMP
            // columns and rejects the second such column without one.
            $table->dateTime('started_at');
            $table->dateTime('expires_at')->index();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('platform_administrators')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['platform_administrator_id', 'status']);
        });

        Schema::create('platform_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('platform_administrator_id')->nullable()->constrained('platform_administrators')->nullOnDelete();
            $table->string('action', 120)->index();
            $table->string('target_type', 190)->nullable();
            $table->string('target_id', 120)->nullable();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
            $table->index(['property_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('platform_support_sessions');
        Schema::dropIfExists('platform_administrators');
    }
};
