<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class DashboardSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * It returns the dashboard summary for the 3 core modules.
     */
    public function test_it_returns_dashboard_summary_payload(): void
    {
        $response = $this->getJson(route('api.v1.dashboard-summary'));

        $response
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data')
                ->whereType('data.generated_at', 'string')
                ->where('data.currency', 'MAD')
                ->whereType('data.stock.total_items', 'integer')
                ->whereType('data.stock.low_stock_items', 'integer')
                ->whereType('data.stock.inventory_value', 'integer|double')
                ->whereType('data.finance.month_income', 'integer|double')
                ->whereType('data.finance.potential_income', 'integer|double')
                ->whereType('data.finance.month_expenses', 'integer|double')
                ->whereType('data.finance.net_result', 'integer|double')
                ->whereType('data.finance.cash_in_hand', 'integer|double')
                ->whereType('data.credits.active_customers', 'integer')
                ->whereType('data.credits.total_due', 'integer|double')
                ->whereType('data.credits.overdue_amount', 'integer|double')
                ->whereType('data.credits.due_this_week', 'integer|double')
                ->has('data.stock.top_categories')
                ->etc());
    }
}
