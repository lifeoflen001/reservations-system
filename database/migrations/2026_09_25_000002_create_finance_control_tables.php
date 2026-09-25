<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('statement_balance', 14, 2);
            $table->decimal('system_balance', 14, 2);
            $table->decimal('difference', 14, 2);
            $table->string('status', 25)->default('pending')->index();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'period_start', 'period_end']);
        });

        Schema::create('daily_cash_closes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->date('close_date');
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->decimal('cash_in', 14, 2)->default(0);
            $table->decimal('cash_out', 14, 2)->default(0);
            $table->decimal('bank_deposits', 14, 2)->default(0);
            $table->decimal('expected_closing_cash', 14, 2)->default(0);
            $table->decimal('actual_counted_cash', 14, 2);
            $table->decimal('variance', 14, 2)->default(0);
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'close_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_cash_closes');
        Schema::dropIfExists('finance_reconciliations');
    }
};
