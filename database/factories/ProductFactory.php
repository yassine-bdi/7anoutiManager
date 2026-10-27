<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchasePrice = fake()->randomFloat(2, 3, 60);

        return [
            'product_category_id' => ProductCategory::factory(),
            'name' => fake()->unique()->words(asText: true, nb: 2),
            'image_path' => null,
            'unit' => fake()->randomElement(['piece', 'kg', 'litre']),
            'stock_quantity' => fake()->randomFloat(3, 0, 220),
            'low_stock_threshold' => fake()->randomFloat(3, 2, 25),
            'purchase_price' => $purchasePrice,
            'selling_price' => round($purchasePrice * fake()->randomFloat(2, 1.1, 1.45), 2),
            'is_active' => true,
        ];
    }
}
