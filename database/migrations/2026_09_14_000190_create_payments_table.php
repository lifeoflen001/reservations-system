<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method');
            $table->string('reference')->nullable()->index();
            $table->dateTime('transaction_date');
            $table->string('status')->default('paid')->index();
            $table->timestamps();
            $table->index(['reservation_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
