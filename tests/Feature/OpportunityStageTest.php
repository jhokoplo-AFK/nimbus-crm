<?php

namespace Tests\Feature;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityStageTest extends TestCase
{
    use RefreshDatabase;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->rep = User::factory()->create();
        $this->rep->assignRole('Sales Rep');
    }

    public function test_a_rep_can_move_an_opportunity_across_the_board(): void
    {
        $opportunity = Opportunity::factory()->create([
            'stage' => OpportunityStage::Qualification,
            'probability_pct' => 25,
        ]);

        $this->actingAs($this->rep)
            ->patch(route('opportunities.stage', $opportunity), ['stage' => OpportunityStage::Proposal->value])
            ->assertRedirect();

        $opportunity->refresh();

        $this->assertSame(OpportunityStage::Proposal, $opportunity->stage);
        $this->assertSame(50, $opportunity->probability_pct);
    }

    public function test_closing_lost_requires_a_loss_reason(): void
    {
        $opportunity = Opportunity::factory()->create(['stage' => OpportunityStage::Negotiation]);

        $this->actingAs($this->rep)
            ->patch(route('opportunities.stage', $opportunity), ['stage' => OpportunityStage::ClosedLost->value])
            ->assertSessionHasErrors('loss_reason');

        $this->assertSame(OpportunityStage::Negotiation, $opportunity->refresh()->stage);
    }

    public function test_closing_lost_records_the_reason_and_zero_probability(): void
    {
        $opportunity = Opportunity::factory()->create([
            'stage' => OpportunityStage::Negotiation,
            'probability_pct' => 75,
        ]);

        $this->actingAs($this->rep)
            ->patch(route('opportunities.stage', $opportunity), [
                'stage' => OpportunityStage::ClosedLost->value,
                'loss_reason' => 'Budget deferred to next fiscal year',
            ])
            ->assertRedirect();

        $opportunity->refresh();

        $this->assertSame(OpportunityStage::ClosedLost, $opportunity->stage);
        $this->assertSame(0, $opportunity->probability_pct);
        $this->assertSame('Budget deferred to next fiscal year', $opportunity->loss_reason);
    }
}
