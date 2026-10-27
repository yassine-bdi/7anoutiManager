<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Boissons',
                'Epicerie seche',
                'Huiles et sauces',
                'Conserves',
                'Produits laitiers',
                'Hygiene',
                'Snacks',
                'Produits menagers',
                'Boulangerie',
                'Confiserie',
                'Petit dejeuner',
                'Tabac',
            ]),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
