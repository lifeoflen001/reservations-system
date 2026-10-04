<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_media', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 32)->default('general');
            $table->string('disk', 32)->default('public');
            $table->string('path', 500)->unique();
            $table->string('filename', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('alt_text', 255)->nullable();
            $table->string('caption', 500)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['category', 'archived_at']);
        });

        Schema::create('website_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 120);
            $table->string('route_name', 120);
            $table->string('status', 16)->default('draft');
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 320)->nullable();
            $table->foreignId('og_media_id')->nullable()->constrained('website_media')->nullOnDelete();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('draft_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['status', 'route_name']);
        });

        Schema::create('website_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('website_pages')->cascadeOnDelete();
            $table->string('section_key', 80);
            $table->string('section_type', 40);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->json('draft_content')->nullable();
            $table->json('published_content')->nullable();
            $table->foreignId('draft_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['page_id', 'section_key']);
            $table->index(['page_id', 'position', 'is_visible']);
        });

        Schema::create('website_navigation_items', function (Blueprint $table): void {
            $table->id();
            $table->string('location', 32)->default('header');
            $table->foreignId('parent_id')->nullable()->constrained('website_navigation_items')->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('destination_type', 16)->default('route');
            $table->string('destination', 255);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->string('status', 16)->default('published');
            $table->string('draft_label', 120)->nullable();
            $table->string('draft_destination', 255)->nullable();
            $table->boolean('draft_visible')->nullable();
            $table->timestamps();
            $table->index(['location', 'status', 'position']);
        });

        Schema::create('website_pricing_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('short_description', 500)->nullable();
            $table->string('price_display', 120)->default('Contact Us');
            $table->string('billing_label', 120)->nullable();
            $table->string('cta_label', 120)->default('Request pricing');
            $table->string('cta_url', 500)->nullable();
            $table->boolean('is_highlighted')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'is_active', 'position']);
        });

        Schema::create('website_pricing_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('website_pricing_plans')->cascadeOnDelete();
            $table->string('feature', 255);
            $table->boolean('included')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['plan_id', 'position']);
        });

        Schema::create('website_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('value')->nullable();
            $table->string('type', 16)->default('string');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('website_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('website_pages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['page_id', 'version']);
        });

        Schema::create('website_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('target_type', 120)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['target_type', 'target_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_audit_logs');
        Schema::dropIfExists('website_revisions');
        Schema::dropIfExists('website_settings');
        Schema::dropIfExists('website_pricing_features');
        Schema::dropIfExists('website_pricing_plans');
        Schema::dropIfExists('website_navigation_items');
        Schema::dropIfExists('website_sections');
        Schema::dropIfExists('website_pages');
        Schema::dropIfExists('website_media');
    }
};
