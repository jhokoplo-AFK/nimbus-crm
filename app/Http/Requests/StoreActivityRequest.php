<?php

namespace App\Http\Requests;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    /**
     * Morph aliases a user is allowed to attach an activity to.
     */
    public const RELATABLE_TYPES = ['lead', 'account', 'contact', 'opportunity'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'relatable_type' => ['required', 'string', Rule::in(self::RELATABLE_TYPES)],
            'relatable_id' => ['required', 'integer', 'min:1'],
            'subject' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ActivityType::class)],
            'status' => ['nullable', Rule::enum(ActivityStatus::class)],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
