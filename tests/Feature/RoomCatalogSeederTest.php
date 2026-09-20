<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use Database\Seeders\RoomCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_catalog_seed_populates_the_database_backed_modules(): void
    {
        $this->seed(RoomCatalogSeeder::class);

        $this->assertSame(3, RoomCategory::count());
        $this->assertSame(3, RoomType::count());
        $this->assertSame(5, Room::count());
        $this->assertSame('Floor 2', Room::where('room_number', '103')->firstOrFail()->floor->name);
        $this->assertSame('Premium', Room::where('room_number', '103')->firstOrFail()->roomType->name);

        $this->seed(RoomCatalogSeeder::class);

        $this->assertSame(3, RoomCategory::count());
        $this->assertSame(3, RoomType::count());
        $this->assertSame(5, Room::count());
    }
}
