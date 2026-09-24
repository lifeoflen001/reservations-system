<?php

namespace Database\Seeders;

use App\Models\PosCategory;
use App\Models\PosOutlet;
use App\Models\PosProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PosDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('pos_outlets')) return;

        $outlets = [
            ['name' => 'Main Restaurant', 'code' => 'main-restaurant', 'location' => 'Ground floor'],
            ['name' => 'Pool Bar', 'code' => 'pool-bar', 'location' => 'Pool deck'],
            ['name' => 'Room Service', 'code' => 'room-service', 'location' => 'Guest rooms'],
            ['name' => 'Front Desk', 'code' => 'front-desk', 'location' => 'Lobby'],
            ['name' => 'Spa', 'code' => 'spa', 'location' => 'Wellness floor'],
            ['name' => 'Minibar', 'code' => 'minibar', 'location' => 'Guest rooms'],
        ];
        $outletModels = collect($outlets)->mapWithKeys(fn (array $outlet) => [
            $outlet['code'] => PosOutlet::firstOrCreate(['code' => $outlet['code']], $outlet + ['is_active' => true]),
        ]);

        $categories = [
            ['name' => 'Food', 'code' => 'food'],
            ['name' => 'Beverages', 'code' => 'beverages'],
            ['name' => 'Laundry', 'code' => 'laundry'],
            ['name' => 'Spa', 'code' => 'spa'],
            ['name' => 'Minibar', 'code' => 'minibar'],
            ['name' => 'Services', 'code' => 'services'],
        ];
        $categoryModels = collect($categories)->mapWithKeys(fn (array $category) => [
            $category['code'] => PosCategory::firstOrCreate(['code' => $category['code']], $category + ['is_active' => true]),
        ]);

        foreach ([
            ['name' => 'Beef Burger', 'sku' => 'FOOD-BURGER', 'category' => 'food', 'outlet' => 'main-restaurant', 'price' => 18000],
            ['name' => 'Grilled Chicken', 'sku' => 'FOOD-CHICKEN', 'category' => 'food', 'outlet' => 'main-restaurant', 'price' => 24000],
            ['name' => 'Bottled Water', 'sku' => 'BEV-WATER', 'category' => 'beverages', 'outlet' => 'pool-bar', 'price' => 2500],
            ['name' => 'Coffee', 'sku' => 'BEV-COFFEE', 'category' => 'beverages', 'outlet' => 'main-restaurant', 'price' => 5000],
            ['name' => 'Fresh Juice', 'sku' => 'BEV-JUICE', 'category' => 'beverages', 'outlet' => 'pool-bar', 'price' => 7000],
            ['name' => 'Laundry Service', 'sku' => 'SRV-LAUNDRY', 'category' => 'laundry', 'outlet' => 'room-service', 'price' => 12000],
            ['name' => 'Minibar Soda', 'sku' => 'MIN-SODA', 'category' => 'minibar', 'outlet' => 'minibar', 'price' => 3500],
            ['name' => 'Airport Transfer', 'sku' => 'SRV-AIRPORT', 'category' => 'services', 'outlet' => 'front-desk', 'price' => 45000],
        ] as $product) {
            PosProduct::firstOrCreate(
                ['sku' => $product['sku']],
                [
                    'category_id' => $categoryModels[$product['category']]->id,
                    'outlet_id' => $outletModels[$product['outlet']]->id,
                    'name' => $product['name'],
                    'selling_price' => $product['price'],
                    'tax_rate' => 0,
                    'is_active' => true,
                    'track_stock' => false,
                    'stock_quantity' => 0,
                    'reorder_level' => 0,
                ],
            );
        }
    }
}
