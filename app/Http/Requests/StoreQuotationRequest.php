<?php

namespace App\Http\Requests;

use App\Enums\DiscountType;
use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Quotation::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opportunity_id' => ['required', 'integer', 'exists:opportunities,id'],
            'pricebook_id' => ['required', 'integer', 'exists:pricebooks,id'],
            'expiration_date' => ['nullable', 'date', 'after:today'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100000000',
                Rule::when(
                    $this->input('discount_type') === DiscountType::Percent->value,
                    ['max:100'],
                ),
            ],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],

            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'lines.*.discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'A quotation needs at least one product line.',
            'lines.*.product_id.exists' => 'One of the selected products no longer exists.',
        ];
    }
}
