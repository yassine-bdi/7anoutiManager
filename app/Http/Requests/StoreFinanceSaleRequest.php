<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFinanceSaleRequest extends FormRequest
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
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'product_id' => ['nullable', 'integer', 'exists:products,id', 'required_without:items'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'required_with:product_id'],
            'occurred_on' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,card,bank,transfer,mobile,credit'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'note' => ['nullable', 'string', 'max:500'],
            'new_customer' => ['nullable', 'array'],
            'new_customer.name' => ['nullable', 'string', 'max:160'],
            'new_customer.phone' => ['nullable', 'string', 'max:40'],
            'new_customer.address' => ['nullable', 'string', 'max:255'],
            'new_customer.notes' => ['nullable', 'string', 'max:500'],
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
                $hasSingleItem = $this->filled('product_id');

                if (! $hasItems && ! $hasSingleItem) {
                    $validator->errors()->add(
                        'items',
                        'Ajoutez au moins un produit a la vente.'
                    );

                    return;
                }

                $paymentMethod = (string) $this->input('payment_method', '');

                if ($paymentMethod !== 'credit') {
                    return;
                }

                $hasCustomerId = $this->filled('customer_id');
                $newCustomerName = trim((string) data_get($this->input('new_customer', []), 'name', ''));

                if (! $hasCustomerId && $newCustomerName === '') {
                    $validator->errors()->add(
                        'customer_id',
                        'Selectionnez un client existant ou ajoutez un nouveau client.'
                    );
                }
            },
        ];
    }
}
