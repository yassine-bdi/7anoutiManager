<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCredit>
 */
class CustomerCreditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalAmount = fake()->randomFloat(2, 80, 2200);
        $status = fake()->randomElement(['open', 'partial', 'paid']);
        $paidAmount = match ($status) {
            'open' => 0,
            'partial' => fake()->randomFloat(2, 1, $totalAmount - 1),
            'paid' => $totalAmount,
        };

        return [
            'customer_id' => Customer::factory(),
            'total_amount' => $totalAmount,
            'paid_amount' => round($paidAmount, 2),
            'status' => $status,
            'opened_on' => fake()->dateTimeBetween('-4 months', '-1 day')->format('Y-m-d'),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
