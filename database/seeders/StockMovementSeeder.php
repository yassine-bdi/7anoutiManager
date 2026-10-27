<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;

class StockMovementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::query()->get();

        foreach ($products as $product) {
            StockMovement::factory()
                ->count(3)
                ->create([
                    'product_id' => $product->id,
                ]);
        }
    }
}
