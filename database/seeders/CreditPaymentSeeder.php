<?php

namespace Database\Seeders;

use App\Models\CreditPayment;
use App\Models\CustomerCredit;
use Illuminate\Database\Seeder;

class CreditPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $credits = CustomerCredit::query()
            ->where('paid_amount', '>', 0)
            ->get();

        foreach ($credits as $credit) {
            CreditPayment::query()->create([
                'customer_credit_id' => $credit->id,
                'amount' => $credit->paid_amount,
                'paid_at' => now()->subDays(fake()->numberBetween(1, 30)),
                'note' => 'Paiement enregistre automatiquement.',
            ]);
        }
    }
}
