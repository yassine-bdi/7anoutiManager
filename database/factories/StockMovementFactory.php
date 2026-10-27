<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $movementType = fake()->randomElement(['in', 'out', 'adjustment']);

        return [
            'product_id' => Product::factory(),
            'movement_type' => $movementType,
            'quantity' => fake()->randomFloat(3, 1, 40),
            'unit_cost' => $movementType === 'out' ? null : fake()->randomFloat(2, 2, 55),
            'note' => fake()->optional()->sentence(),
            'moved_at' => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }
}
