<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StockOverviewResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockOverviewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): StockOverviewResource
    {
        $inventoryValue = (float) Product::query()
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(stock_quantity * purchase_price), 0) as total')
            ->value('total');

        $topCategories = ProductCategory::query()
            ->withCount([
                'products as items' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderByDesc('items')
            ->limit(5)
            ->get()
            ->map(fn (ProductCategory $category): array => [
                'name' => $category->name,
                'items' => (int) $category->items,
            ])
            ->values()
            ->all();

        return new StockOverviewResource([
            'generated_at' => now()->toIso8601String(),
            'currency' => 'MAD',
            'total_items' => Product::query()->where('is_active', true)->count(),
            'low_stock_items' => Product::query()
                ->where('is_active', true)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                ->count(),
            'inventory_value' => $inventoryValue,
            'movements_this_month' => StockMovement::query()
                ->whereBetween('moved_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'top_categories' => $topCategories,
        ]);
    }
}
