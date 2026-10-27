<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStockMovementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'not_in:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'product_id' => ['nullable', 'integer', 'exists:products,id', 'required_without:items'],
            'movement_type' => ['required', 'string', 'in:in,out,adjustment'],
            'quantity' => ['nullable', 'numeric', 'not_in:0', 'required_without:items'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'moved_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rawItems = $this->input('items');
                $hasItems = is_array($rawItems) && count($rawItems) > 0;
                $hasSingleItem = $this->filled('product_id') && $this->filled('quantity');

                if (! $hasItems && ! $hasSingleItem) {
                    $validator->errors()->add(
                        'items',
                        'Ajoutez au moins un produit pour le mouvement de stock.'
                    );
                }

                if ((string) $this->input('movement_type') !== 'in') {
                    return;
                }

                if ($hasItems && is_array($rawItems)) {
                    foreach ($rawItems as $index => $item) {
                        $hasUnitCost = is_array($item)
                            && array_key_exists('unit_cost', $item)
                            && $item['unit_cost'] !== null
                            && $item['unit_cost'] !== '';
                        $hasSellingPrice = is_array($item)
                            && array_key_exists('selling_price', $item)
                            && $item['selling_price'] !== null
                            && $item['selling_price'] !== '';

                        if ($hasUnitCost && ! $hasSellingPrice) {
                            $validator->errors()->add(
                                "items.$index.selling_price",
                                'Renseignez aussi le prix de vente quand le prix d achat est modifie.'
                            );
                        }
                    }

                    return;
                }

                $hasSingleUnitCost = $this->filled('unit_cost');
                $hasSingleSellingPrice = $this->filled('selling_price');

                if ($hasSingleUnitCost && ! $hasSingleSellingPrice) {
                    $validator->errors()->add(
                        'selling_price',
                        'Renseignez aussi le prix de vente quand le prix d achat est modifie.'
                    );
                }
            },
        ];
    }
}
