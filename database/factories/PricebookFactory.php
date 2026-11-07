<?php

namespace Database\Factories;

use App\Models\Pricebook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pricebook>
 */
class PricebookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Price Book',
            'is_standard' => false,
            'is_active' => true,
        ];
    }

    public function standard(): static
    {
        return $this->state(fn (): array => ['is_standard' => true, 'is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
