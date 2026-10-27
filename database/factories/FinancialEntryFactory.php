<?php

namespace Database\Factories;

use App\Models\FinancialEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialEntry>
 */
class FinancialEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entryType = fake()->randomElement(['income', 'expense']);

        return [
            'entry_type' => $entryType,
            'category' => $entryType === 'income'
                ? fake()->randomElement(['Ventes comptoir', 'Ventes livraison'])
                : fake()->randomElement(['Achats fournisseurs', 'Transport', 'Electricite', 'Loyer']),
            'amount' => fake()->randomFloat(2, 30, 2500),
            'occurred_on' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'payment_method' => fake()->randomElement(['cash', 'card', 'bank']),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
