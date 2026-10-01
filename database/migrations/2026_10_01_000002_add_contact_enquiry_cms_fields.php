<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_enquiries', function (Blueprint $table): void {
            $table->foreignId('assigned_to')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable()->after('message');
            $table->timestamp('read_at')->nullable()->after('status');
            $table->timestamp('replied_at')->nullable()->after('read_at');
            $table->timestamp('closed_at')->nullable()->after('replied_at');
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('contact_enquiries', function (Blueprint $table): void {
            $table->dropForeign(['assigned_to']);
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropColumn(['assigned_to', 'internal_notes', 'read_at', 'replied_at', 'closed_at']);
        });
    }
};
