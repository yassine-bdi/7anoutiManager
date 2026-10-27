<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FinanceOverviewResource;
use App\Models\CustomerCredit;
use App\Models\FinancialEntry;
use Illuminate\Http\Request;

class FinanceOverviewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): FinanceOverviewResource
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $activeStatuses = ['open', 'partial'];

        $monthIncome = (float) FinancialEntry::query()
            ->where('entry_type', 'income')
            ->where('payment_method', '!=', 'credit')
            ->whereBetween('occurred_on', [$monthStart, $monthEnd])
            ->sum('amount');

        $monthExpenses = (float) FinancialEntry::query()
            ->where('entry_type', 'expense')
            ->whereBetween('occurred_on', [$monthStart, $monthEnd])
            ->sum('amount');

        $creditOutstanding = (float) CustomerCredit::query()
            ->whereIn('status', $activeStatuses)
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as total_due')
            ->value('total_due');

        $cashInHand = (float) FinancialEntry::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'income' AND payment_method != 'credit' THEN amount WHEN entry_type = 'expense' THEN -amount ELSE 0 END), 0) as balance")
            ->value('balance');

        $topExpenseCategories = FinancialEntry::query()
            ->where('entry_type', 'expense')
            ->whereBetween('occurred_on', [$monthStart, $monthEnd])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn (FinancialEntry $entry): array => [
                'category' => $entry->category,
                'total' => (float) $entry->total,
            ])
            ->values()
            ->all();

        return new FinanceOverviewResource([
            'generated_at' => now()->toIso8601String(),
            'currency' => 'MAD',
            'month_income' => $monthIncome,
            'potential_income' => $monthIncome + $creditOutstanding,
            'month_expenses' => $monthExpenses,
            'net_result' => $monthIncome - $monthExpenses,
            'cash_in_hand' => $cashInHand,
            'entries_this_month' => FinancialEntry::query()
                ->whereBetween('occurred_on', [$monthStart, $monthEnd])
                ->count(),
            'top_expense_categories' => $topExpenseCategories,
        ]);
    }
}
