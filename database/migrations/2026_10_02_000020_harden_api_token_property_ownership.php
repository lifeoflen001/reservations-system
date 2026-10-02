<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $count = DB::table('api_tokens')->whereNull('property_id')->count();
        if ($count > 0) {
            throw new \RuntimeException("Cannot harden api_tokens.property_id: {$count} rows are still unassigned. Revoke or backfill those tokens before migrating.");
        }

        Schema::table('api_tokens', fn (Blueprint $table) => $table->dropForeign(['property_id']));
        Schema::table('api_tokens', fn (Blueprint $table) => $table->unsignedBigInteger('property_id')->nullable(false)->change());
        Schema::table('api_tokens', fn (Blueprint $table) => $table->foreign('property_id')->references('id')->on('properties')->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('api_tokens', fn (Blueprint $table) => $table->dropForeign(['property_id']));
        Schema::table('api_tokens', fn (Blueprint $table) => $table->unsignedBigInteger('property_id')->nullable()->change());
        Schema::table('api_tokens', fn (Blueprint $table) => $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete());
    }
};
