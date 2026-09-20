<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('checked_out_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            $table->timestamp('no_show_at')->nullable()->after('cancellation_reason');
            $table->foreignId('no_show_by')->nullable()->after('no_show_at')->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['cancelled_by']);
            $table->dropForeign(['no_show_by']);
            $table->dropColumn(['updated_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'no_show_at', 'no_show_by', 'deleted_at']);
        });
    }
};
