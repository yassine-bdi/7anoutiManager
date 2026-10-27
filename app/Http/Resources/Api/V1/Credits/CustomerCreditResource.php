<?php

namespace App\Http\Resources\Api\V1\Credits;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerCreditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $remainingAmount = max(0, (float) $this->total_amount - (float) $this->paid_amount);
        $isOverdue = in_array((string) $this->status, ['open', 'partial'], true)
            && $this->due_date
            && $this->due_date->toDateString() < now()->toDateString();

        return [
            'id' => (int) $this->id,
            'customer_id' => (int) $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->customer
                ? [
                    'id' => (int) $this->customer->id,
                    'name' => (string) $this->customer->name,
                    'phone' => $this->customer->phone,
                ]
                : null),
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => $remainingAmount,
            'status' => (string) $this->status,
            'is_overdue' => $isOverdue,
            'opened_on' => $this->opened_on?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'note' => $this->note,
            'payments_count' => (int) ($this->payments_count ?? ($this->relationLoaded('payments') ? $this->payments->count() : 0)),
            'payments' => CreditPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
