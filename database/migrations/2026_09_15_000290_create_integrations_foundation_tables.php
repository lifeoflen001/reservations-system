<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('provider')->nullable();
            $table->string('status')->default('not_configured');
            $table->string('mode')->default('test');
            $table->boolean('is_enabled')->default(false);
            $table->json('settings')->nullable();
            $table->text('secrets')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->json('channels')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('email_delivery_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('recipient');
            $table->string('subject');
            $table->string('template')->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('queued')->index();
            $table->string('provider')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();
            $table->index(['reservation_id', 'status']);
        });

        Schema::create('integration_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('integration')->index();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('direction')->default('outbound');
            $table->string('status')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('error_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_prefix', 24);
            $table->string('token_hash', 64)->unique();
            $table->json('abilities');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('url', 2048);
            $table->text('signing_secret');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('payload');
            $table->string('signature', 128);
            $table->string('status')->default('queued')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();
            $table->index(['event', 'status']);
        });

        Schema::create('webhook_inbound_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->string('event_type')->nullable();
            $table->string('status')->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('channel_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('name');
            $table->string('property_external_id')->nullable();
            $table->string('status')->default('not_configured');
            $table->boolean('is_active')->default(false);
            $table->text('credentials')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['provider', 'status']);
        });

        Schema::create('channel_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->string('external_room_id');
            $table->string('external_room_name');
            $table->foreignId('room_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_rate_plan_id')->nullable();
            $table->string('status')->default('unmapped');
            $table->timestamps();
            $table->unique(['channel_connection_id', 'external_room_id']);
        });

        Schema::create('external_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->string('status')->default('received');
            $table->timestamps();
            $table->unique(['channel_connection_id', 'external_id']);
        });

        Schema::create('gateway_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('external_transaction_id');
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status')->default('pending');
            $table->string('reference')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_transaction_id']);
        });

        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->json('channels');
            $table->json('categories');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('gateway_transactions');
        Schema::dropIfExists('external_reservations');
        Schema::dropIfExists('channel_mappings');
        Schema::dropIfExists('channel_connections');
        Schema::dropIfExists('webhook_inbound_events');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('email_delivery_logs');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('integration_settings');
    }
};
