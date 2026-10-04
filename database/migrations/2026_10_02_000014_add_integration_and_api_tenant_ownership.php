<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_settings', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->after('organization_id')->constrained('properties')->nullOnDelete();
            $table->index(['organization_id', 'property_id', 'key'], 'integration_settings_tenant_key_index');
        });

        Schema::table('webhook_endpoints', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->after('organization_id')->constrained('properties')->nullOnDelete();
            $table->index(['organization_id', 'property_id', 'is_active'], 'webhook_endpoints_tenant_active_index');
        });

        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('user_id')->constrained('organizations')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->after('organization_id')->constrained('properties')->nullOnDelete();
            $table->index(['organization_id', 'property_id', 'revoked_at'], 'api_tokens_tenant_revoked_index');
        });

        foreach (['integration_logs', 'webhook_deliveries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
                $table->foreignId('property_id')->nullable()->after('organization_id')->constrained('properties')->nullOnDelete();
                $table->index(['organization_id', 'property_id', 'created_at'], $tableName.'_tenant_created_index');
            });
        }

        Schema::table('integration_settings', function (Blueprint $table): void {
            $table->dropUnique('integration_settings_key_unique');
        });
        Schema::table('integration_settings', function (Blueprint $table): void {
            $table->unique(['organization_id', 'property_id', 'key'], 'integration_settings_tenant_key_unique');
        });

        $property = DB::table('properties')->whereNotNull('organization_id')->where('status', 'active')->orderBy('id')->first();
        if ($property) {
            DB::table('integration_settings')->whereNull('organization_id')->update(['organization_id' => $property->organization_id, 'property_id' => $property->id]);
            DB::table('webhook_endpoints')->whereNull('organization_id')->update(['organization_id' => $property->organization_id, 'property_id' => $property->id]);
        }

        DB::table('api_tokens')->whereNull('organization_id')->orderBy('id')->chunkById(100, function ($tokens): void {
            foreach ($tokens as $token) {
                $property = DB::table('property_memberships')
                    ->join('organization_memberships', 'organization_memberships.id', '=', 'property_memberships.membership_id')
                    ->join('properties', 'properties.id', '=', 'property_memberships.property_id')
                    ->where('organization_memberships.user_id', $token->user_id)
                    ->where('organization_memberships.status', 'active')
                    ->where('property_memberships.status', 'active')
                    ->where('properties.status', 'active')
                    ->orderBy('properties.id')
                    ->select(['properties.id as property_id', 'properties.organization_id'])
                    ->first();
                if ($property) {
                    DB::table('api_tokens')->where('id', $token->id)->update(['organization_id' => $property->organization_id, 'property_id' => $property->property_id]);
                }
            }
        });

        $anchor = DB::table('properties')->whereNotNull('organization_id')->where('status', 'active')->orderBy('id')->first();
        if ($anchor) {
            DB::table('integration_logs')->whereNull('organization_id')->update(['organization_id' => $anchor->organization_id, 'property_id' => $anchor->id]);
        }
        DB::table('webhook_deliveries')->whereNull('organization_id')->orderBy('id')->chunkById(100, function ($deliveries): void {
            foreach ($deliveries as $delivery) {
                $endpoint = DB::table('webhook_endpoints')->where('id', $delivery->webhook_endpoint_id)->first();
                if ($endpoint?->organization_id) {
                    DB::table('webhook_deliveries')->where('id', $delivery->id)->update(['organization_id' => $endpoint->organization_id, 'property_id' => $endpoint->property_id]);
                }
            }
        });
    }

    public function down(): void
    {
        foreach (['webhook_deliveries', 'integration_logs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_tenant_created_index');
                $table->dropForeign(['organization_id']);
                $table->dropForeign(['property_id']);
                $table->dropColumn(['organization_id', 'property_id']);
            });
        }

        Schema::table('integration_settings', function (Blueprint $table): void {
            $table->dropUnique('integration_settings_tenant_key_unique');
            $table->unique('key');
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['property_id']);
            $table->dropIndex('integration_settings_tenant_key_index');
            $table->dropColumn(['organization_id', 'property_id']);
        });
        Schema::table('webhook_endpoints', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['property_id']);
            $table->dropIndex('webhook_endpoints_tenant_active_index');
            $table->dropColumn(['organization_id', 'property_id']);
        });
        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['property_id']);
            $table->dropIndex('api_tokens_tenant_revoked_index');
            $table->dropColumn(['organization_id', 'property_id']);
        });
    }
};
