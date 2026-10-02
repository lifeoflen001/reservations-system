<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('property_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membership_id')->constrained('organization_memberships')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->string('access_level', 32)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->unique(['membership_id', 'property_id']);
            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_memberships');
        Schema::dropIfExists('organization_memberships');
    }
};
