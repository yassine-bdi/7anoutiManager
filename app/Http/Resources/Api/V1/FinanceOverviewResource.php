<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinanceOverviewResource extends JsonResource
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
            'month_income' => (float) ($this['month_income'] ?? 0),
            'potential_income' => (float) ($this['potential_income'] ?? 0),
            'month_expenses' => (float) ($this['month_expenses'] ?? 0),
            'net_result' => (float) ($this['net_result'] ?? 0),
            'cash_in_hand' => (float) ($this['cash_in_hand'] ?? 0),
            'entries_this_month' => (int) ($this['entries_this_month'] ?? 0),
            'top_expense_categories' => collect($this['top_expense_categories'] ?? [])
                ->map(fn (array $item): array => [
                    'category' => (string) ($item['category'] ?? ''),
                    'total' => (float) ($item['total'] ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }
}
