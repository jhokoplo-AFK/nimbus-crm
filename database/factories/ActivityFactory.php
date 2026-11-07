<?php

namespace Database\Factories;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(4),
            'type' => fake()->randomElement(ActivityType::cases()),
            'due_date' => now()->addDays(fake()->numberBetween(0, 14))->toDateString(),
            'status' => ActivityStatus::Open,
            'relatable_type' => 'opportunity',
            'relatable_id' => Opportunity::factory(),
            'user_id' => User::factory(),
        ];
    }
}
