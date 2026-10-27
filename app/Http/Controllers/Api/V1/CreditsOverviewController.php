<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CreditsOverviewResource;
use App\Models\Customer;
use App\Models\CustomerCredit;
use Illuminate\Http\Request;

class CreditsOverviewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): CreditsOverviewResource
    {
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

        $upcomingCredits = CustomerCredit::query()
            ->with('customer:id,name')
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn (CustomerCredit $credit): array => [
                'customer_name' => $credit->customer?->name ?? 'Client inconnu',
                'due_date' => optional($credit->due_date)->toDateString(),
                'remaining_amount' => (float) ($credit->total_amount - $credit->paid_amount),
            ])
            ->values()
            ->all();

        $topCustomersByDue = Customer::query()
            ->select('customers.id', 'customers.name')
            ->selectSub(
                CustomerCredit::query()
                    ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereIn('status', $activeStatuses),
                'total_due',
            )
            ->whereExists(function ($query) use ($activeStatuses) {
                $query->selectRaw('1')
                    ->from('customer_credits')
                    ->whereColumn('customer_credits.customer_id', 'customers.id')
                    ->whereIn('customer_credits.status', $activeStatuses);
            })
            ->orderByDesc('total_due')
            ->limit(3)
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'total_due' => (float) ($customer->total_due ?? 0),
            ])
            ->values()
            ->all();

        return new CreditsOverviewResource([
            'generated_at' => now()->toIso8601String(),
            'currency' => 'MAD',
            'active_customers' => CustomerCredit::query()
                ->whereIn('status', $activeStatuses)
                ->distinct('customer_id')
                ->count('customer_id'),
            'open_credits' => CustomerCredit::query()->whereIn('status', $activeStatuses)->count(),
            'total_due' => $totalDue,
            'overdue_amount' => $overdueAmount,
            'due_this_week' => $dueThisWeek,
            'upcoming_credits' => $upcomingCredits,
            'top_customers_by_due' => $topCustomersByDue,
        ]);
    }
}
