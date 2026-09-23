<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_delivery_logs', function (Blueprint $table): void {
            $table->foreignId('announcement_recipient_id')->nullable()->after('client_id')->constrained('announcement_recipients')->nullOnDelete();
            $table->index(['announcement_recipient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('email_delivery_logs', function (Blueprint $table): void {
            $table->dropForeign(['announcement_recipient_id']);
            $table->dropIndex(['announcement_recipient_id', 'status']);
            $table->dropColumn('announcement_recipient_id');
        });
    }
};
