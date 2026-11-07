<?php

namespace App\Actions;

use App\Data\QuoteTotals;
use App\Enums\DiscountType;

/**
 * Pure pricing rules for a quotation. No persistence, no Eloquent writes: the
 * action is deterministic, which is what makes it unit-testable and safe to
 * reuse for previews, revisions and re-pricing.
 *
 * Money is rounded to 2 decimals at every step so the stored line items always
 * add up to the stored grand total.
 */
class CalculateQuoteAction
{
    /**
     * @param  array<int, array{product_id?: int, quantity: float|int|string, unit_price: float|int|string, discount_pct?: float|int|string}>  $lines
     */
    public function execute(
        array $lines,
        DiscountType $discountType = DiscountType::None,
        float $discountValue = 0.0,
        ?float $taxRate = null,
    ): QuoteTotals {
        $taxRate = $taxRate ?? (float) config('crm.tax_rate', 11);

        $calculatedLines = [];
        $subtotal = 0.0;
        $grossSubtotal = 0.0;
        $lineDiscountAmount = 0.0;

        foreach ($lines as $line) {
            $quantity = round(max(0.0, (float) $line['quantity']), 2);
            $unitPrice = round(max(0.0, (float) $line['unit_price']), 2);
            $discountPct = round(min(100.0, max(0.0, (float) ($line['discount_pct'] ?? 0))), 2);

            $gross = round($quantity * $unitPrice, 2);
            $lineDiscount = round($gross * ($discountPct / 100), 2);
            $lineTotal = round($gross - $lineDiscount, 2);

            $calculatedLines[] = [
                'product_id' => (int) ($line['product_id'] ?? 0),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_pct' => $discountPct,
                'total_price' => $lineTotal,
            ];

            $grossSubtotal += $gross;
            $lineDiscountAmount += $lineDiscount;
            $subtotal += $lineTotal;
        }

        $subtotal = round($subtotal, 2);
        $grossSubtotal = round($grossSubtotal, 2);
        $lineDiscountAmount = round($lineDiscountAmount, 2);

        $discountAmount = match ($discountType) {
            DiscountType::None => 0.0,
            DiscountType::Percent => round($subtotal * (min(100.0, max(0.0, $discountValue)) / 100), 2),
            DiscountType::Flat => round(min($subtotal, max(0.0, $discountValue)), 2),
        };

        $taxableBase = round(max(0.0, $subtotal - $discountAmount), 2);
        $taxAmount = round($taxableBase * ($taxRate / 100), 2);
        $grandTotal = round($taxableBase + $taxAmount, 2);

        // Line discounts and the quote-wide discount are measured together
        // against the gross price; otherwise a rep could bury 30% in the lines
        // and still look like a clean quote to the approval engine.
        $totalDiscountAmount = round($lineDiscountAmount + $discountAmount, 2);
        $effectiveDiscountPct = $grossSubtotal > 0
            ? round(($totalDiscountAmount / $grossSubtotal) * 100, 2)
            : 0.0;

        return new QuoteTotals(
            subtotal: $subtotal,
            discountAmount: $discountAmount,
            taxableBase: $taxableBase,
            taxRate: round($taxRate, 2),
            taxAmount: $taxAmount,
            grandTotal: $grandTotal,
            effectiveDiscountPct: $effectiveDiscountPct,
            lines: $calculatedLines,
            grossSubtotal: $grossSubtotal,
            lineDiscountAmount: $lineDiscountAmount,
            totalDiscountAmount: $totalDiscountAmount,
        );
    }

    /**
     * Commercial policy gate: any quote that discounts more than the threshold
     * or exceeds the amount threshold must be reviewed by a Sales Manager.
     */
    public function requiresApproval(QuoteTotals $totals): bool
    {
        $discountThreshold = (float) config('crm.approval.discount_threshold_pct', 15);
        $amountThreshold = (float) config('crm.approval.amount_threshold', 50000);

        return $totals->effectiveDiscountPct > $discountThreshold
            || $totals->grandTotal > $amountThreshold;
    }
}
