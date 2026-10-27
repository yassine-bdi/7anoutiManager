<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinancialEntryRequest;
use App\Http\Requests\UpdateFinancialEntryRequest;
use App\Http\Resources\Api\V1\Finance\FinancialEntryResource;
use App\Models\CustomerCredit;
use App\Models\FinancialEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'entry_type' => ['nullable', 'in:income,expense'],
            'category' => ['nullable', 'string', 'max:120'],
            'occurred_from' => ['nullable', 'date'],
            'occurred_to' => ['nullable', 'date', 'after_or_equal:occurred_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = [
            'entry_type' => $validated['entry_type'] ?? null,
            'category' => $validated['category'] ?? null,
            'occurred_from' => $validated['occurred_from'] ?? null,
            'occurred_to' => $validated['occurred_to'] ?? null,
        ];

        $entriesQuery = FinancialEntry::query()
            ->with('customer:id,name,phone')
            ->when($filters['entry_type'], function ($query, string $entryType) {
                $query->where('entry_type', $entryType);
            })
            ->when($filters['category'], function ($query, string $category) {
                $query->where('category', $category);
            })
            ->when($filters['occurred_from'], function ($query, string $occurredFrom) {
                $query->whereDate('occurred_on', '>=', $occurredFrom);
            })
            ->when($filters['occurred_to'], function ($query, string $occurredTo) {
                $query->whereDate('occurred_on', '<=', $occurredTo);
            });

        $entries = (clone $entriesQuery)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 10))
            ->withQueryString();

        $incomeTotal = (float) (clone $entriesQuery)
            ->where('entry_type', 'income')
            ->where('payment_method', '!=', 'credit')
            ->sum('amount');

        $expenseTotal = (float) (clone $entriesQuery)
            ->where('entry_type', 'expense')
            ->sum('amount');

        $creditOutstanding = (float) CustomerCredit::query()
            ->whereIn('status', ['open', 'partial'])
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as total_due')
            ->value('total_due');

        $categories = FinancialEntry::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values()
            ->all();

        return FinancialEntryResource::collection($entries)
            ->additional([
                'meta' => [
                    'filters' => $filters,
                    'totals' => [
                        'income_total' => $incomeTotal,
                        'potential_income' => $incomeTotal + $creditOutstanding,
                        'expense_total' => $expenseTotal,
                        'net_result' => $incomeTotal - $expenseTotal,
                    ],
                    'categories' => $categories,
                ],
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFinancialEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $entry = DB::transaction(function () use ($validated): FinancialEntry {
            $payload = $this->buildFinancialEntryPayload($validated);

            if ($validated['payment_method'] === 'credit') {
                $credit = $this->createCreditFromFinancePayload($validated);
                $payload['customer_credit_id'] = $credit->id;
            }

            return FinancialEntry::query()->create($payload);
        });

        return response()->json([
            'data' => FinancialEntryResource::make($entry->load('customer:id,name,phone')),
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFinancialEntryRequest $request, FinancialEntry $financialEntry): JsonResponse
    {
        $validated = $request->validated();

        $entry = DB::transaction(function () use ($financialEntry, $validated): FinancialEntry {
            $entry = FinancialEntry::query()
                ->lockForUpdate()
                ->findOrFail($financialEntry->id);

            $payload = $this->buildFinancialEntryPayload($validated);

            if ($validated['payment_method'] === 'credit') {
                $credit = $entry->customer_credit_id
                    ? CustomerCredit::query()->lockForUpdate()->find($entry->customer_credit_id)
                    : null;

                if (! $credit instanceof CustomerCredit) {
                    $credit = $this->createCreditFromFinancePayload($validated);
                } else {
                    $paidAmount = (float) $credit->paid_amount;
                    $newTotalAmount = (float) $validated['amount'];

                    if ($paidAmount > $newTotalAmount + 0.0001) {
                        throw ValidationException::withMessages([
                            'amount' => 'Le montant est inferieur au montant deja paye pour cette ardoise.',
                        ]);
                    }

                    $credit->update([
                        'customer_id' => (int) $validated['customer_id'],
                        'total_amount' => round($newTotalAmount, 2),
                        'status' => $this->resolveCreditStatus($newTotalAmount, $paidAmount),
                        'opened_on' => $validated['occurred_on'],
                        'note' => $this->buildCreditNote($validated),
                    ]);
                }

                $payload['customer_credit_id'] = $credit->id;
            } else {
                $this->deleteLinkedCreditWhenPossible($entry);
            }

            $entry->update($payload);

            return $entry->refresh();
        });

        return response()->json([
            'data' => FinancialEntryResource::make($entry->load('customer:id,name,phone')),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FinancialEntry $financialEntry): JsonResponse
    {
        DB::transaction(function () use ($financialEntry): void {
            $entry = FinancialEntry::query()
                ->lockForUpdate()
                ->findOrFail($financialEntry->id);

            $this->deleteLinkedCreditWhenPossible($entry);
            $entry->delete();
        });

        return response()->json([], 204);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildFinancialEntryPayload(array $validated): array
    {
        return [
            'entry_type' => $validated['entry_type'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'occurred_on' => $validated['occurred_on'],
            'payment_method' => $validated['payment_method'],
            'customer_id' => $validated['payment_method'] === 'credit'
                ? (int) $validated['customer_id']
                : null,
            'customer_credit_id' => null,
            'note' => $validated['note'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createCreditFromFinancePayload(array $validated): CustomerCredit
    {
        return CustomerCredit::query()->create([
            'customer_id' => (int) $validated['customer_id'],
            'total_amount' => round((float) $validated['amount'], 2),
            'paid_amount' => 0,
            'status' => 'open',
            'opened_on' => $validated['occurred_on'],
            'due_date' => null,
            'note' => $this->buildCreditNote($validated),
        ]);
    }

    private function deleteLinkedCreditWhenPossible(FinancialEntry $entry): void
    {
        if (! $entry->customer_credit_id) {
            return;
        }

        $credit = CustomerCredit::query()->lockForUpdate()->find($entry->customer_credit_id);

        if (! $credit instanceof CustomerCredit) {
            return;
        }

        if ($credit->payments()->exists()) {
            throw ValidationException::withMessages([
                'payment_method' => 'Impossible de retirer ce credit car des paiements existent deja.',
            ]);
        }

        $credit->delete();
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

    /**
     * @param  array<string, mixed>  $validated
     */
    private function buildCreditNote(array $validated): string
    {
        $base = sprintf(
            'Genere depuis finance (%s - %s).',
            $validated['entry_type'] === 'income' ? 'revenu' : 'depense',
            $validated['category']
        );

        if (! isset($validated['note']) || ! is_string($validated['note']) || trim($validated['note']) === '') {
            return $base;
        }

        return $base.' Note: '.trim($validated['note']);
    }
}
