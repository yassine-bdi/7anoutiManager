<?php

namespace App\Http\Resources\Api\V1\Credits;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'name' => (string) $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'open_credits_count' => (int) ($this->open_credits_count ?? 0),
            'total_due' => (float) ($this->total_due ?? 0),
            'overdue_due' => (float) ($this->overdue_due ?? 0),
        ];
    }
}
