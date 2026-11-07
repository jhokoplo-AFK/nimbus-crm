<?php

namespace App\Http\Requests;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        if (! $lead instanceof Lead || $lead->isLocked()) {
            return false;
        }

        return $this->user()->can('update', $lead);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'lead_source' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(LeadStatus::class), Rule::notIn([LeadStatus::Converted->value])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
