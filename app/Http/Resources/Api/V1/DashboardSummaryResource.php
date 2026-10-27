<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSummaryResource extends JsonResource
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
            'stock' => [
                'total_items' => (int) ($this['stock']['total_items'] ?? 0),
                'low_stock_items' => (int) ($this['stock']['low_stock_items'] ?? 0),
                'inventory_value' => (float) ($this['stock']['inventory_value'] ?? 0),
                'top_categories' => collect($this['stock']['top_categories'] ?? [])
                    ->map(fn (array $category): array => [
                        'name' => (string) ($category['name'] ?? ''),
                        'items' => (int) ($category['items'] ?? 0),
                    ])
                    ->values()
                    ->all(),
            ],
            'finance' => [
                'month_income' => (float) ($this['finance']['month_income'] ?? 0),
                'potential_income' => (float) ($this['finance']['potential_income'] ?? 0),
                'month_expenses' => (float) ($this['finance']['month_expenses'] ?? 0),
                'net_result' => (float) ($this['finance']['net_result'] ?? 0),
                'cash_in_hand' => (float) ($this['finance']['cash_in_hand'] ?? 0),
            ],
            'credits' => [
                'active_customers' => (int) ($this['credits']['active_customers'] ?? 0),
                'total_due' => (float) ($this['credits']['total_due'] ?? 0),
                'overdue_amount' => (float) ($this['credits']['overdue_amount'] ?? 0),
                'due_this_week' => (float) ($this['credits']['due_this_week'] ?? 0),
            ],
        ];
    }
}
