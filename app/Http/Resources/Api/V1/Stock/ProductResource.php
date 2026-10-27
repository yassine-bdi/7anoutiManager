<?php

namespace App\Http\Resources\Api\V1\Stock;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $defaultImageUrl = asset('images/product-default.svg');
        $imageUrl = $this->resolveImageUrl();

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'image_url' => $imageUrl,
            'default_image_url' => $defaultImageUrl,
            'display_image_url' => $imageUrl ?? $defaultImageUrl,
            'unit' => (string) $this->unit,
            'stock_quantity' => (float) $this->stock_quantity,
            'low_stock_threshold' => (float) $this->low_stock_threshold,
            'purchase_price' => (float) $this->purchase_price,
            'selling_price' => (float) $this->selling_price,
            'is_active' => (bool) $this->is_active,
            'is_low_stock' => (float) $this->stock_quantity <= (float) $this->low_stock_threshold,
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category
                ? [
                    'id' => (int) $this->category->id,
                    'name' => (string) $this->category->name,
                ]
                : null),
        ];
    }

    private function resolveImageUrl(): ?string
    {
        $imagePath = is_string($this->image_path) ? trim($this->image_path) : '';

        if ($imagePath === '') {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($imagePath)) {
            return null;
        }

        return asset('storage/'.$imagePath);
    }
}
