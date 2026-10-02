<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pos_outlets', 'pos_categories', 'pos_products', 'pos_shifts', 'pos_orders', 'pos_room_charges', 'pos_audits'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
            });
        }

        Schema::table('pos_outlets', function (Blueprint $table): void {
            $table->dropUnique('pos_outlets_code_unique');
            $table->unique(['property_id', 'code'], 'pos_outlets_property_code_unique');
            $table->index(['property_id', 'is_active']);
        });

        Schema::table('pos_categories', function (Blueprint $table): void {
            $table->dropUnique('pos_categories_name_unique');
            $table->dropUnique('pos_categories_code_unique');
            $table->unique(['property_id', 'name'], 'pos_categories_property_name_unique');
            $table->unique(['property_id', 'code'], 'pos_categories_property_code_unique');
            $table->index(['property_id', 'is_active']);
        });

        Schema::table('pos_products', function (Blueprint $table): void {
            $table->dropUnique('pos_products_sku_unique');
            $table->unique(['property_id', 'sku'], 'pos_products_property_sku_unique');
            $table->index(['property_id', 'category_id', 'is_active']);
            $table->index(['property_id', 'outlet_id', 'is_active']);
        });

        Schema::table('pos_shifts', function (Blueprint $table): void {
            $table->index(['property_id', 'outlet_id', 'status']);
            $table->index(['property_id', 'cashier_id', 'status']);
        });

        Schema::table('pos_orders', function (Blueprint $table): void {
            $table->dropUnique('pos_orders_order_number_unique');
            $table->dropUnique('pos_orders_idempotency_key_unique');
            $table->unique(['property_id', 'order_number'], 'pos_orders_property_number_unique');
            $table->unique(['property_id', 'idempotency_key'], 'pos_orders_property_idempotency_unique');
            $table->index(['property_id', 'outlet_id', 'created_at']);
            $table->index(['property_id', 'status', 'created_at']);
            $table->index(['property_id', 'reservation_id', 'status']);
        });

        Schema::table('pos_room_charges', function (Blueprint $table): void {
            $table->index(['property_id', 'reservation_id', 'status']);
            $table->index(['property_id', 'room_id', 'posted_at']);
        });

        Schema::table('pos_audits', function (Blueprint $table): void {
            $table->index(['property_id', 'created_at']);
            $table->index(['property_id', 'event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_audits', function (Blueprint $table): void {
            $table->dropIndex(['property_id', 'created_at']);
            $table->dropIndex(['property_id', 'event', 'created_at']);
        });
        Schema::table('pos_room_charges', function (Blueprint $table): void {
            $table->dropIndex(['property_id', 'reservation_id', 'status']);
            $table->dropIndex(['property_id', 'room_id', 'posted_at']);
        });
        Schema::table('pos_orders', function (Blueprint $table): void {
            $table->dropUnique('pos_orders_property_number_unique');
            $table->dropUnique('pos_orders_property_idempotency_unique');
            $table->unique('order_number');
            $table->unique('idempotency_key');
            $table->dropIndex(['property_id', 'outlet_id', 'created_at']);
            $table->dropIndex(['property_id', 'status', 'created_at']);
            $table->dropIndex(['property_id', 'reservation_id', 'status']);
        });
        Schema::table('pos_shifts', function (Blueprint $table): void {
            $table->dropIndex(['property_id', 'outlet_id', 'status']);
            $table->dropIndex(['property_id', 'cashier_id', 'status']);
        });
        Schema::table('pos_products', function (Blueprint $table): void {
            $table->dropUnique('pos_products_property_sku_unique');
            $table->unique('sku');
            $table->dropIndex(['property_id', 'category_id', 'is_active']);
            $table->dropIndex(['property_id', 'outlet_id', 'is_active']);
        });
        Schema::table('pos_categories', function (Blueprint $table): void {
            $table->dropUnique('pos_categories_property_name_unique');
            $table->dropUnique('pos_categories_property_code_unique');
            $table->unique('name');
            $table->unique('code');
            $table->dropIndex(['property_id', 'is_active']);
        });
        Schema::table('pos_outlets', function (Blueprint $table): void {
            $table->dropUnique('pos_outlets_property_code_unique');
            $table->unique('code');
            $table->dropIndex(['property_id', 'is_active']);
        });

        foreach (['pos_outlets', 'pos_categories', 'pos_products', 'pos_shifts', 'pos_orders', 'pos_room_charges', 'pos_audits'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['property_id']);
                $table->dropColumn('property_id');
            });
        }
    }
};
