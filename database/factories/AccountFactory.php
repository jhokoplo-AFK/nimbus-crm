<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'industry' => fake()->randomElement(['Manufacturing', 'Financial Services', 'Healthcare', 'Technology', 'Logistics']),
            'annual_revenue' => fake()->randomFloat(2, 500000, 90000000),
            'phone' => fake()->phoneNumber(),
            'website' => 'https://'.fake()->domainName(),
            'billing_address' => fake()->address(),
            'shipping_address' => fake()->address(),
        ];
    }
}
