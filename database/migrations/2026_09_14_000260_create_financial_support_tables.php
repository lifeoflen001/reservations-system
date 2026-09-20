<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
        DB::table('invoice_sequences')->insert(['id' => 1, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->dateTime('issue_date');
            $table->string('status', 20)->default('paid')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Preserve legacy rows when upgrading databases that already contain a
        // repeated reference before enforcing the new audit-safe uniqueness rule.
        $duplicateReferences = DB::table('payments')
            ->select('reference')
            ->whereNotNull('reference')
            ->groupBy('reference')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('reference');
        foreach ($duplicateReferences as $reference) {
            DB::table('payments')->where('reference', $reference)->orderBy('id')->skip(1)->get(['id'])->each(function ($payment) use ($reference): void {
                DB::table('payments')->where('id', $payment->id)->update(['reference' => substr($reference.'-'.$payment->id, 0, 100)]);
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable()->after('transaction_date');
            $table->foreignId('voided_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable()->after('voided_by');
            $table->text('void_reason')->nullable()->after('voided_at');
            $table->index(['status', 'transaction_date']);
            $table->unique('reference');
        });

        foreach (DB::table('payments')->select(['id', 'invoice_number', 'transaction_date', 'status', 'created_by', 'created_at', 'updated_at'])->cursor() as $payment) {
            DB::table('invoices')->insertOrIgnore([
                'payment_id' => $payment->id,
                'invoice_number' => $payment->invoice_number,
                'issue_date' => $payment->transaction_date,
                'status' => $payment->status,
                'created_by' => $payment->created_by,
                'created_at' => $payment->created_at ?? now(),
                'updated_at' => $payment->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropIndex(['status', 'transaction_date']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['updated_by', 'notes', 'voided_by', 'voided_at', 'void_reason']);
        });
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('payment_methods');
    }
};
