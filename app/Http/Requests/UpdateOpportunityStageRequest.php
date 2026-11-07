<?php

namespace App\Http\Requests;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpportunityStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $opportunity = $this->route('opportunity');

        return $opportunity instanceof Opportunity
            && $this->user()->can('updateStage', $opportunity);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(OpportunityStage::class)],
            'loss_reason' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf($this->input('stage') === OpportunityStage::ClosedLost->value),
            ],
        ];
    }
}
