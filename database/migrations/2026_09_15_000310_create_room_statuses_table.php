<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('color', 7);
            $table->boolean('is_sellable')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('room_statuses')->insert([
            ['code' => 'available', 'name' => 'Available', 'color' => '#42c55e', 'is_sellable' => true, 'sort_order' => 10, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'occupied', 'name' => 'Occupied', 'color' => '#25abeb', 'is_sellable' => false, 'sort_order' => 20, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'reserved', 'name' => 'Reserved', 'color' => '#f59e0b', 'is_sellable' => false, 'sort_order' => 30, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'must_clean', 'name' => 'Must clean', 'color' => '#f97316', 'is_sellable' => false, 'sort_order' => 40, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'maintenance', 'name' => 'Maintenance', 'color' => '#ef4444', 'is_sellable' => false, 'sort_order' => 50, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'blocked', 'name' => 'Blocked', 'color' => '#64748b', 'is_sellable' => false, 'sort_order' => 60, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('room_statuses');
    }
};
