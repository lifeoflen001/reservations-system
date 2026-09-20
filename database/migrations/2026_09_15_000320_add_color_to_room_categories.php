<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_categories', function (Blueprint $table) {
            $table->string('color', 7)->default('#69a7e8')->after('description');
        });

        $palette = ['#e67e2f', '#69a7e8', '#d13eb8', '#56a35a', '#df9d28', '#d9534f'];
        DB::table('room_categories')->orderBy('id')->get(['id'])->each(function (object $category, int $index) use ($palette): void {
            DB::table('room_categories')->where('id', $category->id)->update(['color' => $palette[$index % count($palette)]]);
        });
    }

    public function down(): void
    {
        Schema::table('room_categories', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
