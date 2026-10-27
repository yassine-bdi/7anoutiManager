<?php

namespace App\Http\Controllers\Api\V1\Credits;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerCreditRequest;
use App\Http\Resources\Api\V1\Credits\CustomerCreditResource;
use App\Models\Customer;
use App\Models\CustomerCredit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:open,partial,paid'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'overdue_only' => ['nullable', 'boolean'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date', 'after_or_equal:due_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $activeStatuses = ['open', 'partial'];
        $today = now()->toDateString();

        $applyFilters = function ($query) use ($activeStatuses, $today, $validated) {
            return $query
                ->when($validated['status'] ?? null, function ($subQuery, string $status) {
                    $subQuery->where('status', $status);
                })
                ->when($validated['customer_id'] ?? null, function ($subQuery, int $customerId) {
                    $subQuery->where('customer_id', $customerId);
                })
                ->when((bool) ($validated['overdue_only'] ?? false), function ($subQuery) use ($today, $activeStatuses) {
                    $subQuery
                        ->whereIn('status', $activeStatuses)
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '<', $today);
                })
                ->when($validated['due_from'] ?? null, function ($subQuery, string $dueFrom) {
                    $subQuery->whereDate('due_date', '>=', $dueFrom);
                })
                ->when($validated['due_to'] ?? null, function ($subQuery, string $dueTo) {
                    $subQuery->whereDate('due_date', '<=', $dueTo);
                });
        };

        $entriesQuery = $applyFilters(
            CustomerCredit::query()
                ->with([
                    'customer:id,name,phone',
                    'payments' => fn ($query) => $query->orderByDesc('paid_at')->orderByDesc('id')->limit(20),
                ])
                ->withCount('payments')
        );

        $totalsQuery = $applyFilters(CustomerCredit::query());

        $entries = (clone $entriesQuery)
            ->orderByDesc('opened_on')
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 10))
            ->withQueryString();

        $totalDue = (float) (clone $totalsQuery)
            ->whereIn('status', $activeStatuses)
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as total_due')
            ->value('total_due');

        $overdueAmount = (float) (clone $totalsQuery)
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as overdue_due')
            ->value('overdue_due');

        $dueThisWeek = (float) (clone $totalsQuery)
            ->whereIn('status', $activeStatuses)
            ->whereBetween('due_date', [$today, now()->addDays(7)->toDateString()])
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as week_due')
            ->value('week_due');

        $customers = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn (Customer $customer): array => [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'phone' => $customer->phone,
            ])
            ->values()
            ->all();

        return CustomerCreditResource::collection($entries)
            ->additional([
                'meta' => [
                    'filters' => [
                        'status' => $validated['status'] ?? null,
                        'customer_id' => $validated['customer_id'] ?? null,
                        'overdue_only' => (bool) ($validated['overdue_only'] ?? false),
                        'due_from' => $validated['due_from'] ?? null,
                        'due_to' => $validated['due_to'] ?? null,
                    ],
                    'totals' => [
                        'total_due' => $totalDue,
                        'overdue_amount' => $overdueAmount,
                        'due_this_week' => $dueThisWeek,
                    ],
                    'customers' => $customers,
                ],
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerCreditRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $credit = CustomerCredit::query()->create([
            'customer_id' => $validated['customer_id'],
            'total_amount' => $validated['total_amount'],
            'paid_amount' => 0,
            'status' => 'open',
            'opened_on' => $validated['opened_on'] ?? now()->toDateString(),
            'due_date' => $validated['due_date'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'data' => CustomerCreditResource::make($credit->load([
                'customer:id,name,phone',
                'payments' => fn ($query) => $query->orderByDesc('paid_at')->orderByDesc('id'),
            ])),
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreCustomerCreditRequest $request, CustomerCredit $customerCredit): JsonResponse
    {
        $validated = $request->validated();

        $credit = DB::transaction(function () use ($validated, $customerCredit): CustomerCredit {
            $credit = CustomerCredit::query()
                ->lockForUpdate()
                ->findOrFail($customerCredit->id);

            $newTotalAmount = round((float) $validated['total_amount'], 2);
            $paidAmount = (float) $credit->paid_amount;

            if ($paidAmount > $newTotalAmount + 0.0001) {
                throw ValidationException::withMessages([
                    'total_amount' => 'Le total ne peut pas etre inferieur au montant deja paye.',
                ]);
            }

            $credit->update([
                'customer_id' => (int) $validated['customer_id'],
                'total_amount' => $newTotalAmount,
                'status' => $this->resolveCreditStatus($newTotalAmount, $paidAmount),
                'opened_on' => $validated['opened_on'] ?? $credit->opened_on?->toDateString() ?? now()->toDateString(),
                'due_date' => $validated['due_date'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            return $credit->refresh();
        });

        return response()->json([
            'data' => CustomerCreditResource::make($credit->load([
                'customer:id,name,phone',
                'payments' => fn ($query) => $query->orderByDesc('paid_at')->orderByDesc('id'),
            ])),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomerCredit $customerCredit): JsonResponse
    {
        $customerCredit->delete();

        return response()->json([], 204);
    }

    private function resolveCreditStatus(float $totalAmount, float $paidAmount): string
    {
        if ($paidAmount <= 0.0001) {
            return 'open';
        }

        if (abs($totalAmount - $paidAmount) < 0.0001) {
            return 'paid';
        }

        return 'partial';
    }
}
