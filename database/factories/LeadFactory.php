<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company' => fake()->company(),
            'title' => fake()->jobTitle(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'lead_source' => fake()->randomElement(['Web', 'Referral', 'Event', 'Outbound', 'Partner']),
            'status' => LeadStatus::New,
            'notes' => fake()->sentence(),
        ];
    }

    public function qualified(): static
    {
        return $this->state(fn (): array => ['status' => LeadStatus::Qualified]);
    }
}
