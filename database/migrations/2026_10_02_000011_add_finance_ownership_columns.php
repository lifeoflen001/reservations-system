<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['financial_accounts', 'expense_categories', 'financial_transactions', 'expenses', 'fund_transfers', 'finance_reconciliations', 'daily_cash_closes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
            });
        }

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
        });

        Schema::table('financial_accounts', function (Blueprint $table): void {
            $table->dropUnique('financial_accounts_code_unique');
            $table->unique(['property_id', 'code'], 'financial_accounts_property_code_unique');
            $table->index(['property_id', 'type', 'is_active'], 'finance_accounts_property_type_active_index');
        });

        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->dropUnique('expense_categories_name_unique');
            $table->dropUnique('expense_categories_code_unique');
            $table->unique(['property_id', 'name'], 'expense_categories_property_name_unique');
            $table->unique(['property_id', 'code'], 'expense_categories_property_code_unique');
            $table->index(['property_id', 'is_active'], 'expense_categories_property_active_index');
        });

        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropUnique('financial_transactions_transaction_number_unique');
            $table->unique(['property_id', 'transaction_number'], 'financial_transactions_property_number_unique');
            $table->index(['property_id', 'account_id', 'transaction_date', 'status'], 'finance_transactions_property_account_date_status_index');
            $table->index(['property_id', 'created_at'], 'finance_transactions_property_created_index');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropUnique('expenses_expense_number_unique');
            $table->unique(['property_id', 'expense_number'], 'expenses_property_number_unique');
            $table->index(['property_id', 'expense_date', 'status'], 'expenses_property_date_status_index');
            $table->index(['property_id', 'account_id', 'expense_date'], 'expenses_property_account_date_index');
        });

        Schema::table('fund_transfers', function (Blueprint $table): void {
            $table->dropUnique('fund_transfers_transfer_number_unique');
            $table->unique(['property_id', 'transfer_number'], 'fund_transfers_property_number_unique');
            $table->index(['property_id', 'transfer_date', 'status'], 'transfers_property_date_status_index');
        });

        Schema::table('finance_reconciliations', function (Blueprint $table): void {
            $table->index(['property_id', 'account_id', 'period_start', 'period_end'], 'reconciliations_property_account_period_index');
        });

        Schema::table('daily_cash_closes', function (Blueprint $table): void {
            $table->index(['property_id', 'close_date'], 'cash_closes_property_date_index');
        });

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->index(['property_id', 'refunded_at', 'status'], 'refunds_property_date_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', fn (Blueprint $table) => $table->dropIndex('refunds_property_date_status_index'));
        Schema::table('daily_cash_closes', fn (Blueprint $table) => $table->dropIndex('cash_closes_property_date_index'));
        Schema::table('finance_reconciliations', fn (Blueprint $table) => $table->dropIndex('reconciliations_property_account_period_index'));
        Schema::table('fund_transfers', function (Blueprint $table): void {
            $table->dropUnique('fund_transfers_property_number_unique');
            $table->unique('transfer_number');
            $table->dropIndex('transfers_property_date_status_index');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropUnique('expenses_property_number_unique');
            $table->unique('expense_number');
            $table->dropIndex('expenses_property_date_status_index');
            $table->dropIndex('expenses_property_account_date_index');
        });
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropUnique('financial_transactions_property_number_unique');
            $table->unique('transaction_number');
            $table->dropIndex('finance_transactions_property_account_date_status_index');
            $table->dropIndex('finance_transactions_property_created_index');
        });
        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->dropUnique('expense_categories_property_name_unique');
            $table->dropUnique('expense_categories_property_code_unique');
            $table->unique('name');
            $table->unique('code');
            $table->dropIndex('expense_categories_property_active_index');
        });
        Schema::table('financial_accounts', function (Blueprint $table): void {
            $table->dropUnique('financial_accounts_property_code_unique');
            $table->unique('code');
            $table->dropIndex('finance_accounts_property_type_active_index');
        });

        foreach (['financial_accounts', 'expense_categories', 'financial_transactions', 'expenses', 'fund_transfers', 'finance_reconciliations', 'daily_cash_closes', 'payment_refunds'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['property_id']);
                $table->dropColumn('property_id');
            });
        }
    }
};
