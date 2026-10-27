<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinanceSaleRequest;
use App\Http\Resources\Api\V1\Finance\FinancialEntryResource;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\FinancialEntry;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceSaleController extends Controller
{
    /**
     * Enregistre une vente produit en finance et met a jour le stock.
     */
    public function store(StoreFinanceSaleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $saleItems = $this->normalizeSaleItems($validated);

        [$entry, $updatedProducts, $credit] = DB::transaction(function () use ($validated, $saleItems): array {
            $productIds = array_values(array_unique(array_map(
                static fn (array $item): int => (int) $item['product_id'],
                $saleItems
            )));

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'items' => 'Un ou plusieurs produits sont introuvables.',
                ]);
            }

            /** @var array<int, float> $quantitiesByProduct */
            $quantitiesByProduct = [];

            foreach ($saleItems as $item) {
                $productId = (int) $item['product_id'];
                $quantity = (float) $item['quantity'];

                $quantitiesByProduct[$productId] = ($quantitiesByProduct[$productId] ?? 0.0) + $quantity;
            }

            $lineItems = [];
            $updatedProducts = [];
            $saleAmount = 0.0;

            foreach ($quantitiesByProduct as $productId => $quantity) {
                $product = $products->get($productId);

                if (! $product instanceof Product) {
                    continue;
                }

                $currentStock = (float) $product->stock_quantity;
                $newStock = $currentStock - $quantity;

                if ($newStock < -0.0001) {
                    throw ValidationException::withMessages([
                        'items' => sprintf('Stock insuffisant pour %s.', $product->name),
                    ]);
                }

                $lineAmount = round($quantity * (float) $product->selling_price, 2);
                $saleAmount = round($saleAmount + $lineAmount, 2);

                $nextStock = round(max(0, $newStock), 3);

                $product->update([
                    'stock_quantity' => $nextStock,
                ]);

                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'movement_type' => 'out',
                    'quantity' => $quantity,
                    'unit_cost' => (float) $product->purchase_price,
                    'note' => $this->buildStockMovementNote($product, $quantity, $validated['payment_method']),
                    'moved_at' => $validated['occurred_on'],
                ]);

                $lineItems[] = [
                    'product_name' => (string) $product->name,
                    'quantity' => $quantity,
                ];

                $updatedProducts[] = [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'stock_quantity' => (float) $nextStock,
                ];
            }

            $customerId = null;
            $credit = null;

            if ($validated['payment_method'] === 'credit') {
                $customer = $this->resolveCreditCustomer($validated);
                $customerId = (int) $customer->id;

                $credit = CustomerCredit::query()
                    ->lockForUpdate()
                    ->where('customer_id', $customerId)
                    ->whereIn('status', ['open', 'partial'])
                    ->orderByDesc('opened_on')
                    ->orderByDesc('id')
                    ->first();

                if ($credit instanceof CustomerCredit) {
                    $newTotal = round((float) $credit->total_amount + $saleAmount, 2);
                    $paidAmount = (float) $credit->paid_amount;

                    $credit->update([
                        'total_amount' => $newTotal,
                        'status' => $this->resolveCreditStatus($newTotal, $paidAmount),
                        'note' => $this->mergeCreditNote($credit->note, $lineItems, $validated['note'] ?? null),
                    ]);
                } else {
                    $credit = CustomerCredit::query()->create([
                        'customer_id' => $customerId,
                        'total_amount' => $saleAmount,
                        'paid_amount' => 0,
                        'status' => 'open',
                        'opened_on' => $validated['occurred_on'],
                        'due_date' => null,
                        'note' => $this->buildCreditNote($lineItems, $validated['note'] ?? null),
                    ]);
                }
            }

            $entry = FinancialEntry::query()->create([
                'entry_type' => 'income',
                'category' => 'Vente produits',
                'amount' => $saleAmount,
                'occurred_on' => $validated['occurred_on'],
                'payment_method' => $validated['payment_method'],
                'customer_id' => $customerId,
                'customer_credit_id' => $credit?->id,
                'note' => $this->buildFinanceNote($lineItems, $validated['note'] ?? null),
            ]);

            return [$entry, $updatedProducts, $credit?->refresh()];
        });

        return response()->json([
            'data' => [
                'entry' => FinancialEntryResource::make($entry->load('customer:id,name,phone')),
                'product' => $updatedProducts[0] ?? null,
                'products' => $updatedProducts,
                'credit' => $credit
                    ? [
                        'id' => (int) $credit->id,
                        'customer_id' => (int) $credit->customer_id,
                        'total_amount' => (float) $credit->total_amount,
                        'paid_amount' => (float) $credit->paid_amount,
                        'status' => (string) $credit->status,
                    ]
                    : null,
            ],
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array{product_id: int, quantity: float}>
     */
    private function normalizeSaleItems(array $validated): array
    {
        if (isset($validated['items']) && is_array($validated['items']) && count($validated['items']) > 0) {
            return collect($validated['items'])
                ->map(static fn (array $item): array => [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (float) $item['quantity'],
                ])
                ->values()
                ->all();
        }

        return [[
            'product_id' => (int) ($validated['product_id'] ?? 0),
            'quantity' => (float) ($validated['quantity'] ?? 0),
        ]];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveCreditCustomer(array $validated): Customer
    {
        if (! empty($validated['customer_id'])) {
            return Customer::query()->findOrFail((int) $validated['customer_id']);
        }

        $newCustomer = $validated['new_customer'] ?? [];

        return Customer::query()->create([
            'name' => trim((string) ($newCustomer['name'] ?? '')),
            'phone' => isset($newCustomer['phone']) ? trim((string) $newCustomer['phone']) ?: null : null,
            'address' => isset($newCustomer['address']) ? trim((string) $newCustomer['address']) ?: null : null,
            'notes' => isset($newCustomer['notes']) ? trim((string) $newCustomer['notes']) ?: null : null,
            'is_active' => true,
        ]);
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
     * @param  array<int, array{product_name: string, quantity: float}>  $lineItems
     */
    private function buildFinanceNote(array $lineItems, ?string $note): string
    {
        $base = 'Vente produits: '.$this->formatLineItemsSummary($lineItems).'.';

        if (! is_string($note) || trim($note) === '') {
            return $base;
        }

        return $base.' Note: '.trim($note);
    }

    /**
     * @param  array<int, array{product_name: string, quantity: float}>  $lineItems
     */
    private function buildCreditNote(array $lineItems, ?string $note): string
    {
        return $this->buildFinanceNote($lineItems, $note);
    }

    /**
     * @param  array<int, array{product_name: string, quantity: float}>  $lineItems
     */
    private function mergeCreditNote(?string $existingNote, array $lineItems, ?string $note): string
    {
        $newNote = $this->buildCreditNote($lineItems, $note);

        if (! is_string($existingNote) || trim($existingNote) === '') {
            return $newNote;
        }

        return trim($existingNote).' | '.$newNote;
    }

    /**
     * @param  array<int, array{product_name: string, quantity: float}>  $lineItems
     */
    private function formatLineItemsSummary(array $lineItems): string
    {
        $visibleLines = array_slice($lineItems, 0, 5);

        $summary = implode(', ', array_map(
            static fn (array $line): string => sprintf(
                '%s x %s',
                $line['product_name'],
                rtrim(rtrim(sprintf('%.3f', $line['quantity']), '0'), '.')
            ),
            $visibleLines
        ));

        if (count($lineItems) > count($visibleLines)) {
            return $summary.sprintf(' +%d autres', count($lineItems) - count($visibleLines));
        }

        return $summary;
    }

    private function buildStockMovementNote(Product $product, float $quantity, string $paymentMethod): string
    {
        return sprintf(
            'Sortie vente (%s) - %s x %s',
            $paymentMethod,
            $product->name,
            rtrim(rtrim(sprintf('%.3f', $quantity), '0'), '.')
        );
    }
}
