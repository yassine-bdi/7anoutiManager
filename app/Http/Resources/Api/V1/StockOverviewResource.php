<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOverviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'generated_at' => $this['generated_at'] ?? now()->toIso8601String(),
            'currency' => $this['currency'] ?? 'MAD',
            'total_items' => (int) ($this['total_items'] ?? 0),
            'low_stock_items' => (int) ($this['low_stock_items'] ?? 0),
            'inventory_value' => (float) ($this['inventory_value'] ?? 0),
            'movements_this_month' => (int) ($this['movements_this_month'] ?? 0),
            'top_categories' => collect($this['top_categories'] ?? [])
                ->map(fn (array $category): array => [
                    'name' => (string) ($category['name'] ?? ''),
                    'items' => (int) ($category['items'] ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }
}
