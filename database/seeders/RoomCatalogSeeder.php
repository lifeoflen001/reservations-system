<?php

namespace Database\Seeders;

use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [];
        foreach ([
            ['name' => 'Double Room', 'description' => 'Comfortable room for two guests.', 'color' => '#e67e2f', 'sort_order' => 1],
            ['name' => 'Single Room', 'description' => 'Practical room for one guest.', 'color' => '#69a7e8', 'sort_order' => 2],
            ['name' => 'Triple Room', 'description' => 'Spacious room for three guests.', 'color' => '#d13eb8', 'sort_order' => 3],
        ] as $data) {
            $categories[$data['name']] = RoomCategory::firstOrCreate(
                ['name' => $data['name']],
                $data + ['is_active' => true],
            );
        }

        $types = [];
        foreach ([
            ['name' => 'Family', 'description' => 'Family room with one matrimonial bed and one personal bed.', 'capacity' => 3, 'bed_type' => '1 matrimonial bed + 1 person bed', 'base_rate' => 75, 'sort_order' => 1],
            ['name' => 'Matrimonial', 'description' => 'Room with one double bed.', 'capacity' => 2, 'bed_type' => '1 double bed', 'base_rate' => 35, 'sort_order' => 2],
            ['name' => 'Premium', 'description' => 'Premium room with one double bed.', 'capacity' => 2, 'bed_type' => '1 double bed', 'base_rate' => 49, 'sort_order' => 3],
        ] as $data) {
            $types[$data['name']] = RoomType::firstOrCreate(
                ['name' => $data['name']],
                $data + ['is_active' => true],
            );
        }

        $floors = [];
        foreach ([['name' => 'Floor 1', 'sort_order' => 1], ['name' => 'Floor 2', 'sort_order' => 2]] as $data) {
            $floors[$data['name']] = Floor::firstOrCreate(
                ['name' => $data['name']],
                $data + ['is_active' => true],
            );
        }

        foreach ([
            ['room_number' => '100', 'floor' => 'Floor 1', 'category' => 'Double Room', 'type' => 'Matrimonial'],
            ['room_number' => '101', 'floor' => 'Floor 1', 'category' => 'Double Room', 'type' => 'Premium'],
            ['room_number' => '102', 'floor' => 'Floor 1', 'category' => 'Triple Room', 'type' => 'Family'],
            ['room_number' => '103', 'floor' => 'Floor 2', 'category' => 'Single Room', 'type' => 'Premium'],
            ['room_number' => '104', 'floor' => 'Floor 2', 'category' => 'Double Room', 'type' => 'Matrimonial'],
        ] as $data) {
            $type = $types[$data['type']];

            Room::firstOrCreate(
                ['room_number' => $data['room_number']],
                [
                    'floor_id' => $floors[$data['floor']]->id,
                    'room_category_id' => $categories[$data['category']]->id,
                    'room_type_id' => $type->id,
                    'operational_status' => 'available',
                    'housekeeping_status' => 'clean',
                    'base_rate' => $type->base_rate,
                    'capacity' => $type->capacity,
                    'is_active' => true,
                ],
            );
        }
    }
}
