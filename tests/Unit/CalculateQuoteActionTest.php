<?php

namespace Tests\Unit;

use App\Actions\CalculateQuoteAction;
use App\Enums\DiscountType;
use Tests\TestCase;

class CalculateQuoteActionTest extends TestCase
{
    private CalculateQuoteAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CalculateQuoteAction;
    }

    public function test_it_calculates_line_totals_discount_and_tax(): void
    {
        $totals = $this->action->execute(
            [
                ['product_id' => 1, 'quantity' => 2, 'unit_price' => 1000, 'discount_pct' => 10],
                ['product_id' => 2, 'quantity' => 1, 'unit_price' => 500],
            ],
            DiscountType::Percent,
            5,
            11,
        );

        // Line 1: 2 x 1000 less 10% = 1800. Line 2: 500. Subtotal = 2300.
        $this->assertEqualsWithDelta(2300.0, $totals->subtotal, 0.001);
        $this->assertEqualsWithDelta(115.0, $totals->discountAmount, 0.001);
        $this->assertEqualsWithDelta(2185.0, $totals->taxableBase, 0.001);
        $this->assertEqualsWithDelta(240.35, $totals->taxAmount, 0.001);
        $this->assertEqualsWithDelta(2425.35, $totals->grandTotal, 0.001);
        $this->assertEqualsWithDelta(5.0, $totals->effectiveDiscountPct, 0.001);
    }

    public function test_a_flat_discount_cannot_exceed_the_subtotal(): void
    {
        $totals = $this->action->execute(
            [['product_id' => 1, 'quantity' => 1, 'unit_price' => 400]],
            DiscountType::Flat,
            10000,
            11,
        );

        $this->assertEqualsWithDelta(400.0, $totals->subtotal, 0.001);
        $this->assertEqualsWithDelta(400.0, $totals->discountAmount, 0.001);
        $this->assertEqualsWithDelta(0.0, $totals->taxableBase, 0.001);
        $this->assertEqualsWithDelta(0.0, $totals->grandTotal, 0.001);
    }

    public function test_discount_above_threshold_requires_approval(): void
    {
        $totals = $this->action->execute(
            [['product_id' => 1, 'quantity' => 1, 'unit_price' => 1000]],
            DiscountType::Percent,
            16,
        );

        $this->assertTrue($this->action->requiresApproval($totals));
    }

    public function test_amount_above_threshold_requires_approval(): void
    {
        $totals = $this->action->execute(
            [['product_id' => 1, 'quantity' => 1, 'unit_price' => 50000]],
            DiscountType::None,
        );

        // 50,000 + 11% PPN is above the 50,000 amount threshold.
        $this->assertEqualsWithDelta(55500.0, $totals->grandTotal, 0.001);
        $this->assertTrue($this->action->requiresApproval($totals));
    }

    public function test_small_clean_quote_does_not_require_approval(): void
    {
        $totals = $this->action->execute(
            [['product_id' => 1, 'quantity' => 2, 'unit_price' => 2500]],
            DiscountType::Percent,
            10,
        );

        $this->assertFalse($this->action->requiresApproval($totals));
    }
}
