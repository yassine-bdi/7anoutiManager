<?php

namespace App\Http\Controllers\Api\V1\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockMovementRequest;
use App\Http\Resources\Api\V1\Stock\ProductResource;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementController extends Controller
{
    /**
     * Store a new stock movement and apply it to the product stock quantity.
     */
    public function store(StoreStockMovementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $movementType = $validated['movement_type'];
        $movementItems = $this->normalizeMovementItems($validated, $movementType);

        [$updatedProducts, $movements] = DB::transaction(function () use ($movementItems, $movementType, $validated): array {
            $productIds = array_values(array_unique(array_map(
                static fn (array $item): int => (int) $item['product_id'],
                $movementItems
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

            /** @var array<int, array{quantity: float, unit_cost: float|null, selling_price: float|null}> $aggregatedByProduct */
            $aggregatedByProduct = [];

            foreach ($movementItems as $item) {
                $productId = (int) $item['product_id'];
                $quantity = (float) $item['quantity'];
                $unitCost = array_key_exists('unit_cost', $item) ? $item['unit_cost'] : null;
                $sellingPrice = array_key_exists('selling_price', $item) ? $item['selling_price'] : null;

                if (! isset($aggregatedByProduct[$productId])) {
                    $aggregatedByProduct[$productId] = [
                        'quantity' => 0.0,
                        'unit_cost' => null,
                        'selling_price' => null,
                    ];
                }

                $aggregatedByProduct[$productId]['quantity'] += $quantity;

                if ($unitCost !== null && $unitCost !== '') {
                    $aggregatedByProduct[$productId]['unit_cost'] = (float) $unitCost;
                }

                if ($sellingPrice !== null && $sellingPrice !== '') {
                    $aggregatedByProduct[$productId]['selling_price'] = (float) $sellingPrice;
                }
            }

            $updatedProducts = [];
            $movements = [];

            foreach ($aggregatedByProduct as $productId => $payload) {
                $product = $products->get($productId);

                if (! $product instanceof Product) {
                    continue;
                }

                $signedQuantity = $movementType === 'adjustment'
                    ? (float) $payload['quantity']
                    : abs((float) $payload['quantity']);

                $currentStock = (float) $product->stock_quantity;
                $newStock = $currentStock;

                if ($movementType === 'in') {
                    $newStock = $currentStock + $signedQuantity;
                }

                if ($movementType === 'out') {
                    $newStock = $currentStock - $signedQuantity;
                }

                if ($movementType === 'adjustment') {
                    $newStock = $currentStock + $signedQuantity;
                }

                if ($newStock < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stock insuffisant pour ce mouvement.',
                    ]);
                }

                $resolvedUnitCost = $payload['unit_cost'] !== null
                    ? round((float) $payload['unit_cost'], 2)
                    : round((float) $product->purchase_price, 2);

                $updatePayload = [
                    'stock_quantity' => round($newStock, 3),
                ];

                if ($movementType === 'in' && $payload['unit_cost'] !== null) {
                    $updatePayload['purchase_price'] = $resolvedUnitCost;
                    $updatePayload['selling_price'] = round((float) $payload['selling_price'], 2);
                }

                $product->update($updatePayload);

                $movement = StockMovement::query()->create([
                    'product_id' => $product->id,
                    'movement_type' => $movementType,
                    'quantity' => $signedQuantity,
                    'unit_cost' => $resolvedUnitCost,
                    'note' => $validated['note'] ?? null,
                    'moved_at' => $validated['moved_at'] ?? now(),
                ]);

                $updatedProducts[] = ProductResource::make($product->refresh()->load('category:id,name'))->resolve();
                $movements[] = [
                    'id' => (int) $movement->id,
                    'product_id' => (int) $movement->product_id,
                    'movement_type' => (string) $movement->movement_type,
                    'quantity' => (float) $movement->quantity,
                    'unit_cost' => (float) $movement->unit_cost,
                    'moved_at' => $movement->moved_at?->toIso8601String(),
                ];
            }

            return [$updatedProducts, $movements];
        });

        return response()->json([
            'data' => [
                'movement' => $movements[0] ?? null,
                'movements' => $movements,
                'product' => $updatedProducts[0] ?? null,
                'products' => $updatedProducts,
            ],
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array{product_id: int, quantity: float, unit_cost: float|null, selling_price: float|null}>
     */
    private function normalizeMovementItems(array $validated, string $movementType): array
    {
        if (isset($validated['items']) && is_array($validated['items']) && count($validated['items']) > 0) {
            return collect($validated['items'])
                ->map(static function (array $item) use ($movementType): array {
                    $rawQuantity = (float) ($item['quantity'] ?? 0);
                    $quantity = $movementType === 'adjustment' ? $rawQuantity : abs($rawQuantity);

                    return [
                        'product_id' => (int) $item['product_id'],
                        'quantity' => $quantity,
                        'unit_cost' => array_key_exists('unit_cost', $item) && $item['unit_cost'] !== null && $item['unit_cost'] !== ''
                            ? (float) $item['unit_cost']
                            : null,
                        'selling_price' => array_key_exists('selling_price', $item) && $item['selling_price'] !== null && $item['selling_price'] !== ''
                            ? (float) $item['selling_price']
                            : null,
                    ];
                })
                ->values()
                ->all();
        }

        $rawQuantity = (float) ($validated['quantity'] ?? 0);
        $quantity = $movementType === 'adjustment' ? $rawQuantity : abs($rawQuantity);

        return [[
            'product_id' => (int) ($validated['product_id'] ?? 0),
            'quantity' => $quantity,
            'unit_cost' => isset($validated['unit_cost']) ? (float) $validated['unit_cost'] : null,
            'selling_price' => isset($validated['selling_price']) ? (float) $validated['selling_price'] : null,
        ]];
    }
}
