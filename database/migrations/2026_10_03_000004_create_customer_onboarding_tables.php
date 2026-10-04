<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->boolean('is_onboarding_eligible')->default(false)->index();
            $table->boolean('is_onboarding_default')->default(false)->index();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('email_verification_required')->default(false)->index();
            $table->timestamp('registration_terms_accepted_at')->nullable();
            $table->string('registration_terms_version', 80)->nullable();
            $table->string('registration_source', 40)->nullable()->index();
        });

        Schema::create('organization_onboardings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('current_step', 40)->default('organization')->index();
            $table->boolean('organization_completed')->default(false);
            $table->boolean('plan_completed')->default(false);
            $table->boolean('property_completed')->default(false);
            $table->boolean('hotel_setup_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['owner_user_id', 'completed_at']);
        });

        Schema::create('organization_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('email', 254);
            $table->string('status', 24)->default('pending')->index();
            $table->string('token_hash', 128)->unique();
            $table->timestamp('expires_at')->index();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'email', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('organization_invitation_properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invitation_id')->constrained('organization_invitations')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['invitation_id', 'property_id'], 'org_invitation_property_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitation_properties');
        Schema::dropIfExists('organization_invitations');
        Schema::dropIfExists('organization_onboardings');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['registration_source']);
            $table->dropIndex(['email_verification_required']);
            $table->dropColumn(['email_verification_required', 'registration_terms_accepted_at', 'registration_terms_version', 'registration_source']);
        });
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropIndex(['is_onboarding_eligible']);
            $table->dropIndex(['is_onboarding_default']);
            $table->dropColumn(['is_onboarding_eligible', 'is_onboarding_default']);
        });
    }
};
