<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = ProductCategory::query()->get();

        if ($categories->isEmpty()) {
            $categories = ProductCategory::factory()->count(5)->create();
        }

        foreach ($categories as $category) {
            Product::factory()
                ->count(6)
                ->create([
                    'product_category_id' => $category->id,
                ]);
        }
    }
}
