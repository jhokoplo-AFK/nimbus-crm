<?php

namespace App\Http\Requests;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Lead::class);
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
            'status' => [
                'required',
                Rule::enum(LeadStatus::class),
                // Conversion is a workflow action, never a manual status edit.
                Rule::notIn([LeadStatus::Converted->value]),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
