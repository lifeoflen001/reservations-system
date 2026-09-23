<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('category')->default('Company News');
            $table->text('short_description');
            $table->longText('content');
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_high_priority')->default(false);
            $table->boolean('is_company_wide')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'start_at', 'end_at']);
            $table->index(['category', 'is_featured', 'is_high_priority']);
        });

        Schema::create('announcement_department', function (Blueprint $table): void {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['announcement_id', 'department_id']);
        });

        Schema::create('announcement_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('audience_source')->default('company-wide');
            $table->string('email_status')->default('not_sent');
            $table->string('browser_status')->default('not_sent');
            $table->timestamp('in_app_sent_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('browser_sent_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
            $table->index(['user_id', 'first_viewed_at']);
        });

        Schema::create('announcement_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('stored_name');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('announcement_id');
        });

        Schema::create('announcement_push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Browser push endpoints are normally below 500 chars. Keep the
            // indexed value within MySQL's utf8mb4 index-size limit.
            $table->string('endpoint', 700);
            $table->text('public_key')->nullable();
            $table->text('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'endpoint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_push_subscriptions');
        Schema::dropIfExists('announcement_attachments');
        Schema::dropIfExists('announcement_recipients');
        Schema::dropIfExists('announcement_department');
        Schema::dropIfExists('announcements');
    }
};
