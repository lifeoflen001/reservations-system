<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_outlets', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 40)->unique();
            $table->string('location')->nullable();
            $table->json('default_payment_methods')->nullable();
            $table->text('receipt_header')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('pos_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 40)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('pos_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('pos_categories')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('pos_outlets')->nullOnDelete();
            $table->string('name', 160);
            $table->string('sku', 80)->nullable()->unique();
            $table->text('description')->nullable();
            $table->decimal('selling_price', 12, 2);
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->boolean('tax_inclusive')->default(false);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('track_stock')->default(false);
            $table->decimal('stock_quantity', 12, 3)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->index(['category_id', 'is_active']);
            $table->index(['outlet_id', 'is_active']);
        });

        Schema::create('pos_order_sequences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
        DB::table('pos_order_sequences')->insert(['id' => 1, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('pos_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outlet_id')->constrained('pos_outlets')->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->dateTime('opened_at');
            $table->decimal('cash_sales', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->default(0);
            $table->decimal('actual_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['cashier_id', 'outlet_id', 'status']);
        });

        Schema::create('pos_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->foreignId('outlet_id')->constrained('pos_outlets')->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'created_at']);
            $table->index(['cashier_id', 'created_at']);
            $table->index(['shift_id', 'status']);
            $table->index(['reservation_id', 'status']);
            $table->index(['room_id', 'status']);
        });

        Schema::create('pos_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('pos_products')->nullOnDelete();
            $table->string('product_name_snapshot', 160);
            $table->string('sku_snapshot', 80)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'product_id']);
        });

        Schema::create('pos_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->string('method', 60);
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable();
            $table->string('status', 20)->default('paid')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'method']);
        });

        Schema::create('pos_room_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('pos_orders')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('active')->index();
            $table->dateTime('posted_at');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['reservation_id', 'status']);
            $table->index(['room_id', 'posted_at']);
        });

        Schema::create('pos_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('pos_orders')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 500);
            $table->foreignId('refunded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('refunded_at');
            $table->timestamps();
            $table->index(['order_id', 'refunded_at']);
        });

        Schema::create('pos_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 80)->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('pos_orders')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('pos_products')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        DB::table('payment_methods')->insertOrIgnore([
            ['code' => 'mobile_money', 'name' => 'Mobile money', 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'charge_to_room', 'name' => 'Charge to room', 'is_active' => true, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_audits');
        Schema::dropIfExists('pos_refunds');
        Schema::dropIfExists('pos_room_charges');
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('pos_shifts');
        Schema::dropIfExists('pos_order_sequences');
        Schema::dropIfExists('pos_products');
        Schema::dropIfExists('pos_categories');
        Schema::dropIfExists('pos_outlets');
    }
};
