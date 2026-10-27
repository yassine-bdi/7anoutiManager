<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerCredit;
use Illuminate\Database\Seeder;

class CustomerCreditSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::query()->get();

        foreach ($customers as $customer) {
            if (fake()->boolean(60)) {
                CustomerCredit::factory()->create([
                    'customer_id' => $customer->id,
                ]);
            }
        }
    }
}
