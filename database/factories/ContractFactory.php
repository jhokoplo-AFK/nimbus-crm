<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Account;
use App\Models\Contract;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'opportunity_id' => Opportunity::factory(),
            'contract_number' => 'CT-'.now()->format('Y').'-'.fake()->unique()->numerify('#####'),
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->addYear()->endOfMonth()->toDateString(),
            'status' => ContractStatus::Draft,
            'terms_and_conditions' => 'Standard Nimbus Master Services Agreement applies.',
        ];
    }
}
