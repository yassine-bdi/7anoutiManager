<?php

namespace App\Http\Resources\Api\V1\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'entry_type' => (string) $this->entry_type,
            'category' => (string) $this->category,
            'amount' => (float) $this->amount,
            'occurred_on' => $this->occurred_on?->toDateString(),
            'payment_method' => (string) $this->payment_method,
            'customer_id' => $this->customer_id ? (int) $this->customer_id : null,
            'customer_credit_id' => $this->customer_credit_id ? (int) $this->customer_credit_id : null,
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->customer
                ? [
                    'id' => (int) $this->customer->id,
                    'name' => (string) $this->customer->name,
                    'phone' => $this->customer->phone,
                ]
                : null),
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
