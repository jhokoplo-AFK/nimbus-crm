<?php

namespace Database\Factories;

use App\Models\SalesTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesTarget>
 */
class SalesTargetFactory extends Factory
{
    /**
     * Company-wide annual target by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'brand_id' => null,
            'account_id' => null,
            'product_id' => null,
            'year' => now()->year,
            'quarter' => null,
            'month' => null,
            'target_amount' => fake()->randomFloat(2, 50000, 2000000),
        ];
    }

    public function forMonth(int $month): static
    {
        return $this->state(fn (): array => ['month' => $month]);
    }

    public function forQuarter(int $quarter): static
    {
        return $this->state(fn (): array => ['quarter' => $quarter]);
    }
}
