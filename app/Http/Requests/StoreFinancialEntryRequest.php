<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFinancialEntryRequest extends FormRequest
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
            'entry_type' => ['required', 'string', 'in:income,expense'],
            'category' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'occurred_on' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,card,bank,transfer,mobile,credit'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id', 'required_if:payment_method,credit'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('payment_method') === 'credit' && $this->input('entry_type') !== 'income') {
                    $validator->errors()->add(
                        'payment_method',
                        'Le paiement credit est reserve aux revenus clients.'
                    );
                }
            },
        ];
    }
}
