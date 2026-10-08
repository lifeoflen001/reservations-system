<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateway_transactions', function (Blueprint $table): void {
            $table->dropUnique('gateway_transactions_provider_external_transaction_id_unique');
            $table->unique(['property_id', 'provider', 'external_transaction_id'], 'gateway_transactions_property_provider_external_unique');
        });

        Schema::table('webhook_inbound_events', function (Blueprint $table): void {
            $table->dropUnique('webhook_inbound_events_provider_event_id_unique');
            $table->unique(['organization_id', 'property_id', 'provider', 'event_id'], 'webhook_inbound_tenant_provider_event_unique');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_inbound_events', function (Blueprint $table): void {
            $table->dropUnique('webhook_inbound_tenant_provider_event_unique');
            $table->unique(['provider', 'event_id']);
        });

        Schema::table('gateway_transactions', function (Blueprint $table): void {
            $table->dropUnique('gateway_transactions_property_provider_external_unique');
            $table->unique(['provider', 'external_transaction_id']);
        });
    }
};
