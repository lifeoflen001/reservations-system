<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('attachment_type', 40)->nullable()->after('attachment_path');
            $table->dateTime('submitted_at')->nullable()->after('created_by');
            $table->dateTime('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->foreignId('reversed_by')->nullable()->after('ledger_transaction_id')->constrained('users')->nullOnDelete();
            $table->dateTime('reversed_at')->nullable()->after('reversed_by');
            $table->text('reversal_reason')->nullable()->after('reversed_at');
            $table->foreignId('reversal_transaction_id')->nullable()->after('reversal_reason')->constrained('financial_transactions')->nullOnDelete();
            $table->index(['status', 'expense_date']);
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 60);
            $table->string('refund_reference', 100)->unique();
            $table->text('reason');
            $table->string('status', 20)->default('posted')->index();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('refunded_at');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->timestamps();
            $table->index(['payment_id', 'status', 'refunded_at']);
        });

        // Idempotency is enforced in the database as well as in the service
        // lock. Existing duplicates must be investigated rather than silently
        // deleted during an incremental migration.
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->unique(['source_type', 'source_id', 'transaction_type', 'direction'], 'financial_transactions_source_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropUnique('financial_transactions_source_identity_unique');
        });
        Schema::dropIfExists('payment_refunds');
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['reversed_by']);
            $table->dropForeign(['reversal_transaction_id']);
            $table->dropIndex(['status', 'expense_date']);
            $table->dropColumn(['attachment_type', 'submitted_at', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason', 'reversed_by', 'reversed_at', 'reversal_reason', 'reversal_transaction_id']);
        });
    }
};
