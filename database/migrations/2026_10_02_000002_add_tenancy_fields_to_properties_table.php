<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('uuid')->nullable()->after('organization_id')->unique();
            $table->string('slug', 160)->nullable()->after('name');
            $table->string('property_code', 64)->nullable()->after('slug');
            $table->string('status', 24)->default('active')->after('property_code')->index();
            $table->json('settings')->nullable()->after('status');
            $table->index('organization_id');
            $table->unique(['organization_id', 'slug'], 'properties_organization_slug_unique');
            $table->unique(['organization_id', 'property_code'], 'properties_organization_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropUnique('properties_organization_code_unique');
            $table->dropUnique('properties_organization_slug_unique');
            $table->dropIndex(['organization_id']);
            $table->dropUnique(['uuid']);
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['uuid', 'slug', 'property_code', 'status', 'settings']);
        });
    }
};
