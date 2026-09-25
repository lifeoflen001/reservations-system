<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_sequences', function (Blueprint $table): void {
            $table->string('key', 20)->primary();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
        foreach (['transaction', 'expense', 'transfer'] as $key) {
            \Illuminate\Support\Facades\DB::table('finance_sequences')->insert(['key' => $key, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 50)->unique();
            $table->string('type', 30)->index();
            $table->string('currency', 10)->default('USD');
            $table->string('bank_name', 120)->nullable();
            $table->text('account_number')->nullable();
            $table->string('branch', 120)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 50)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_number', 40)->unique();
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->string('transaction_type', 40)->index();
            $table->string('direction', 10);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('reference', 120)->nullable()->index();
            $table->text('description')->nullable();
            $table->dateTime('transaction_date')->index();
            $table->string('source_type', 160)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 25)->default('posted')->index();
            $table->dateTime('reversed_at')->nullable();
            $table->foreignId('reversal_transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
            $table->index(['account_id', 'transaction_date', 'status']);
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('expense_number', 40)->unique();
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('payment_method', 60)->nullable();
            $table->string('payee', 160)->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('description');
            $table->string('attachment_path')->nullable();
            $table->dateTime('expense_date')->index();
            $table->string('status', 25)->default('posted')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->timestamps();
            $table->index(['department_id', 'expense_date']);
        });

        Schema::create('fund_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('transfer_number', 40)->unique();
            $table->foreignId('from_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('reference', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('attachment_path')->nullable();
            $table->dateTime('transfer_date')->index();
            $table->string('status', 25)->default('posted')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('debit_transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->foreignId('credit_transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->timestamps();
            $table->index(['from_account_id', 'to_account_id', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_transfers');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('financial_accounts');
        Schema::dropIfExists('finance_sequences');
    }
};
