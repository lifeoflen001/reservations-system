<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_support_sessions', function (Blueprint $table): void {
            $table->dateTime('entered_at')->nullable()->after('started_at');
            $table->dateTime('last_activity_at')->nullable()->after('entered_at');
        });
    }

    public function down(): void
    {
        Schema::table('platform_support_sessions', function (Blueprint $table): void {
            $table->dropColumn(['entered_at', 'last_activity_at']);
        });
    }
};
