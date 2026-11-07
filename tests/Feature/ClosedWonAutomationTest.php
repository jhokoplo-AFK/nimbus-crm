<?php

namespace Tests\Feature;

use App\Enums\OpportunityStage;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosedWonAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_an_opportunity_closed_won_locks_probability_and_creates_a_draft_order(): void
    {
        $opportunity = Opportunity::factory()->create([
            'stage' => OpportunityStage::Negotiation,
            'amount' => 75000,
            'probability_pct' => 75,
        ]);

        $opportunity->update(['stage' => OpportunityStage::ClosedWon]);

        $opportunity->refresh();

        $this->assertSame(OpportunityStage::ClosedWon, $opportunity->stage);
        $this->assertSame(100, $opportunity->probability_pct);

        $order = SalesOrder::query()->where('opportunity_id', $opportunity->id)->first();

        $this->assertNotNull($order);
        $this->assertSame(SalesOrderStatus::Draft, $order->status);
        $this->assertEqualsWithDelta(75000.0, (float) $order->total_amount, 0.001);
        $this->assertSame($opportunity->account_id, $order->account_id);
        $this->assertStringStartsWith('SO-', $order->order_number);
    }

    public function test_the_automation_is_idempotent(): void
    {
        $opportunity = Opportunity::factory()->create([
            'stage' => OpportunityStage::Negotiation,
            'amount' => 12000,
        ]);

        $opportunity->update(['stage' => OpportunityStage::ClosedWon]);
        $opportunity->update(['stage' => OpportunityStage::ClosedWon]);
        $opportunity->update(['name' => 'Renamed after close']);

        $this->assertSame(
            1,
            SalesOrder::query()->where('opportunity_id', $opportunity->id)->count(),
        );
    }

    public function test_an_approved_quotation_total_is_used_for_the_order(): void
    {
        $opportunity = Opportunity::factory()->create([
            'stage' => OpportunityStage::Negotiation,
            'amount' => 1000,
        ]);

        Quotation::factory()->create([
            'opportunity_id' => $opportunity->id,
            'status' => QuotationStatus::Approved,
            'grand_total' => 43210.55,
            'revision_number' => 2,
            'is_primary' => true,
        ]);

        $opportunity->update(['stage' => OpportunityStage::ClosedWon]);

        $order = SalesOrder::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

        $this->assertEqualsWithDelta(43210.55, (float) $order->total_amount, 0.001);
    }
}
