<?php

namespace Database\Factories;

use App\Enums\OpportunityStage;
use App\Models\Account;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stage = fake()->randomElement([
            OpportunityStage::Prospecting,
            OpportunityStage::Qualification,
            OpportunityStage::Proposal,
            OpportunityStage::Negotiation,
        ]);

        return [
            'account_id' => Account::factory(),
            'owner_id' => null,
            'primary_contact_id' => null,
            'name' => fake()->catchPhrase(),
            'stage' => $stage,
            'amount' => fake()->randomFloat(2, 5000, 250000),
            'probability_pct' => $stage->defaultProbability(),
            'close_date' => now()->addDays(fake()->numberBetween(7, 90))->toDateString(),
            'loss_reason' => null,
        ];
    }

    public function won(): static
    {
        return $this->state(fn (): array => [
            'stage' => OpportunityStage::ClosedWon,
            'probability_pct' => 100,
            'close_date' => now()->subDays(5)->toDateString(),
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn (): array => [
            'stage' => OpportunityStage::ClosedLost,
            'probability_pct' => 0,
            'loss_reason' => 'Lost to competitor on price',
            'close_date' => now()->subDays(10)->toDateString(),
        ]);
    }
}
