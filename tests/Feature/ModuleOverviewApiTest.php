<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\FinancialEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ModuleOverviewApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * It returns stock overview metrics.
     */
    public function test_it_returns_stock_overview(): void
    {
        $this->seedScenario();

        $response = $this->getJson(route('api.v1.stock.overview'));

        $response
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->where('data.total_items', 2)
                ->where('data.low_stock_items', 1)
                ->where('data.inventory_value', 320)
                ->has('data.top_categories', 1)
                ->etc());
    }

    /**
     * It returns finance overview metrics.
     */
    public function test_it_returns_finance_overview(): void
    {
        $this->seedScenario();

        $response = $this->getJson(route('api.v1.finance.overview'));

        $response
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->where('data.month_income', 1200)
                ->where('data.potential_income', 1800)
                ->where('data.month_expenses', 450)
                ->where('data.net_result', 750)
                ->where('data.cash_in_hand', 750)
                ->where('data.entries_this_month', 2)
                ->etc());
    }

    /**
     * It returns credits overview metrics.
     */
    public function test_it_returns_credits_overview(): void
    {
        $this->seedScenario();

        $response = $this->getJson(route('api.v1.credits.overview'));

        $response
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->where('data.active_customers', 1)
                ->where('data.open_credits', 2)
                ->where('data.total_due', 600)
                ->where('data.overdue_amount', 400)
                ->where('data.due_this_week', 200)
                ->has('data.top_customers_by_due', 1)
                ->where('data.top_customers_by_due.0.name', 'Client Hanout')
                ->where('data.top_customers_by_due.0.total_due', 600)
                ->etc());
    }

    private function seedScenario(): void
    {
        $category = ProductCategory::factory()->create([
            'name' => 'Boissons',
        ]);

        Product::factory()->create([
            'product_category_id' => $category->id,
            'stock_quantity' => 2,
            'low_stock_threshold' => 5,
            'purchase_price' => 10,
        ]);

        Product::factory()->create([
            'product_category_id' => $category->id,
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
            'purchase_price' => 15,
        ]);

        FinancialEntry::query()->create([
            'entry_type' => 'income',
            'category' => 'Ventes comptoir',
            'amount' => 1200,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        FinancialEntry::query()->create([
            'entry_type' => 'expense',
            'category' => 'Achats fournisseurs',
            'amount' => 450,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $customer = Customer::factory()->create([
            'name' => 'Client Hanout',
        ]);

        CustomerCredit::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 500,
            'paid_amount' => 100,
            'status' => 'partial',
            'opened_on' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        CustomerCredit::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 200,
            'paid_amount' => 0,
            'status' => 'open',
            'opened_on' => now()->subDays(8)->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ]);
    }
}
