<?php

namespace Database\Factories;

use App\Models\CreditPayment;
use App\Models\CustomerCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditPayment>
 */
class CreditPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_credit_id' => CustomerCredit::factory(),
            'amount' => fake()->randomFloat(2, 20, 600),
            'paid_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
