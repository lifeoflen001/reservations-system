<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropForeign(['floor_id']);
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->foreignId('floor_id')->nullable()->change();
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->foreign('floor_id')->references('id')->on('floors')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropForeign(['floor_id']);
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->foreignId('floor_id')->nullable(false)->change();
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->foreign('floor_id')->references('id')->on('floors')->restrictOnDelete();
        });
    }
};
