<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DashboardSummaryResource;
use App\Models\CustomerCredit;
use App\Models\FinancialEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class DashboardSummaryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): DashboardSummaryResource
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $monthIncome = (float) FinancialEntry::query()
            ->where('entry_type', 'income')
            ->where('payment_method', '!=', 'credit')
            ->whereBetween('occurred_on', [$monthStart, $monthEnd])
            ->sum('amount');

        $monthExpenses = (float) FinancialEntry::query()
            ->where('entry_type', 'expense')
            ->whereBetween('occurred_on', [$monthStart, $monthEnd])
            ->sum('amount');

        $inventoryValue = (float) Product::query()
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(stock_quantity * purchase_price), 0) as total')
            ->value('total');

        $topCategories = ProductCategory::query()
            ->withCount([
                'products as items' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderByDesc('items')
            ->limit(3)
            ->get()
            ->map(fn (ProductCategory $category): array => [
                'name' => $category->name,
                'items' => (int) $category->items,
            ])
            ->values()
            ->all();

        $activeStatuses = ['open', 'partial'];
        $today = now()->toDateString();

        $totalDue = (float) CustomerCredit::query()
            ->whereIn('status', $activeStatuses)
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as total_due')
            ->value('total_due');

        $overdueAmount = (float) CustomerCredit::query()
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as overdue_due')
            ->value('overdue_due');

        $dueThisWeek = (float) CustomerCredit::query()
            ->whereIn('status', $activeStatuses)
            ->whereBetween('due_date', [$today, now()->addDays(7)->toDateString()])
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as week_due')
            ->value('week_due');

        $cashInHand = (float) FinancialEntry::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'income' AND payment_method != 'credit' THEN amount WHEN entry_type = 'expense' THEN -amount ELSE 0 END), 0) as balance")
            ->value('balance');

        return new DashboardSummaryResource([
            'generated_at' => now()->toIso8601String(),
            'currency' => 'MAD',
            'stock' => [
                'total_items' => Product::query()->where('is_active', true)->count(),
                'low_stock_items' => Product::query()
                    ->where('is_active', true)
                    ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                    ->count(),
                'inventory_value' => $inventoryValue,
                'top_categories' => $topCategories,
            ],
            'finance' => [
                'month_income' => $monthIncome,
                'potential_income' => $monthIncome + $totalDue,
                'month_expenses' => $monthExpenses,
                'net_result' => $monthIncome - $monthExpenses,
                'cash_in_hand' => $cashInHand,
            ],
            'credits' => [
                'active_customers' => CustomerCredit::query()
                    ->whereIn('status', $activeStatuses)
                    ->distinct('customer_id')
                    ->count('customer_id'),
                'total_due' => $totalDue,
                'overdue_amount' => $overdueAmount,
                'due_this_week' => $dueThisWeek,
            ],
        ]);
    }
}
