<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('target_type', 160)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at'], 'org_audit_org_created_idx');
            $table->index(['organization_id', 'property_id', 'created_at'], 'org_audit_org_property_created_idx');
            $table->index(['organization_id', 'target_user_id', 'created_at'], 'org_audit_org_target_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_audit_logs');
    }
};
