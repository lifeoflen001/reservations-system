<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Base64 keeps the payload portable across MySQL and SQLite while
            // avoiding Railway's ephemeral application filesystem.
            $table->longText('avatar_data')->nullable()->after('avatar_path');
            $table->string('avatar_mime', 100)->nullable()->after('avatar_data');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['avatar_data', 'avatar_mime']);
        });
    }
};
