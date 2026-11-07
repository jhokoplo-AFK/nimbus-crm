<?php

namespace App\Data;

final readonly class QuoteTotals
{
    /**
     * @param  array<int, array{product_id: int, quantity: float, unit_price: float, discount_pct: float, total_price: float}>  $lines
     */
    public function __construct(
        public float $subtotal,
        public float $discountAmount,
        public float $taxableBase,
        public float $taxRate,
        public float $taxAmount,
        public float $grandTotal,
        public float $effectiveDiscountPct,
        public array $lines = [],
        public float $grossSubtotal = 0.0,
        public float $lineDiscountAmount = 0.0,
        public float $totalDiscountAmount = 0.0,
    ) {}

    /**
     * @return array<string, float|array<int, array<string, float|int>>>
     */
    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'gross_subtotal' => $this->grossSubtotal,
            'line_discount_amount' => $this->lineDiscountAmount,
            'total_discount_amount' => $this->totalDiscountAmount,
            'discount_amount' => $this->discountAmount,
            'taxable_base' => $this->taxableBase,
            'tax_rate' => $this->taxRate,
            'tax_amount' => $this->taxAmount,
            'grand_total' => $this->grandTotal,
            'effective_discount_pct' => $this->effectiveDiscountPct,
            'lines' => $this->lines,
        ];
    }
}
