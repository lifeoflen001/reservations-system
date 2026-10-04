<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_inbound_events', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->after('organization_id')->constrained('properties')->nullOnDelete();
            $table->index(['organization_id', 'property_id', 'provider', 'event_id'], 'webhook_inbound_tenant_event_index');
        });

        $count = DB::table('webhook_inbound_events')->count();
        if ($count > 0) {
            $properties = DB::table('properties')->where('status', 'active')->whereNotNull('organization_id')->orderBy('id')->get();
            if ($properties->count() !== 1) {
                throw new \RuntimeException('Cannot assign existing webhook inbound events safely: map them to a tenant before migrating.');
            }
            DB::table('webhook_inbound_events')->update([
                'organization_id' => $properties->first()->organization_id,
                'property_id' => $properties->first()->id,
            ]);
        }

        Schema::table('webhook_inbound_events', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('webhook_inbound_events', function (Blueprint $table): void {
            $table->dropIndex('webhook_inbound_tenant_event_index');
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['property_id']);
            $table->dropColumn(['organization_id', 'property_id']);
        });
    }
};
