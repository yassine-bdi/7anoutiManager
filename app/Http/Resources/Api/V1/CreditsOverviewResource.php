<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditsOverviewResource extends JsonResource
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
            'active_customers' => (int) ($this['active_customers'] ?? 0),
            'open_credits' => (int) ($this['open_credits'] ?? 0),
            'total_due' => (float) ($this['total_due'] ?? 0),
            'overdue_amount' => (float) ($this['overdue_amount'] ?? 0),
            'due_this_week' => (float) ($this['due_this_week'] ?? 0),
            'upcoming_credits' => collect($this['upcoming_credits'] ?? [])
                ->map(fn (array $item): array => [
                    'customer_name' => (string) ($item['customer_name'] ?? ''),
                    'due_date' => $item['due_date'] ?? null,
                    'remaining_amount' => (float) ($item['remaining_amount'] ?? 0),
                ])
                ->values()
                ->all(),
            'top_customers_by_due' => collect($this['top_customers_by_due'] ?? [])
                ->map(fn (array $item): array => [
                    'id' => (int) ($item['id'] ?? 0),
                    'name' => (string) ($item['name'] ?? ''),
                    'total_due' => (float) ($item['total_due'] ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }
}
