<?php

namespace App\Http\Requests;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Opportunity::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'primary_contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'name' => ['required', 'string', 'max:255'],
            'stage' => ['required', Rule::enum(OpportunityStage::class)],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'probability_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'close_date' => ['nullable', 'date'],
            'loss_reason' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf($this->input('stage') === OpportunityStage::ClosedLost->value),
            ],
        ];
    }
}
