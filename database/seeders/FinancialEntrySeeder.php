<?php

namespace Database\Seeders;

use App\Models\FinancialEntry;
use Illuminate\Database\Seeder;

class FinancialEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FinancialEntry::factory()->count(35)->create();
    }
}
