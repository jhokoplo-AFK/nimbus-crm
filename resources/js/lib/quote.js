const round2 = (value) => Math.round((Number(value) + Number.EPSILON) * 100) / 100;

/**
 * Mirrors App\Actions\CalculateQuoteAction so the builder can preview totals
 * instantly. The server remains authoritative: it recalculates everything and
 * ignores any price the browser sends.
 */
export function calculateQuote({ lines = [], discountType = 'none', discountValue = 0, taxRate = 11 }) {
    const computedLines = lines.map((line) => {
        const quantity = Math.max(0, Number(line.quantity) || 0);
        const unitPrice = Math.max(0, Number(line.unit_price) || 0);
        const discountPct = Math.min(100, Math.max(0, Number(line.discount_pct) || 0));

        const gross = round2(quantity * unitPrice);
        const lineDiscount = round2((gross * discountPct) / 100);
        const total = round2(gross - lineDiscount);

        return {
            ...line,
            quantity,
            unit_price: unitPrice,
            discount_pct: discountPct,
            gross,
            lineDiscount,
            total,
        };
    });

    const subtotal = round2(computedLines.reduce((sum, line) => sum + line.total, 0));
    const grossSubtotal = round2(computedLines.reduce((sum, line) => sum + line.gross, 0));
    const lineDiscountAmount = round2(computedLines.reduce((sum, line) => sum + line.lineDiscount, 0));

    const rawDiscount = Math.max(0, Number(discountValue) || 0);
    const discountAmount =
        discountType === 'percent'
            ? round2((subtotal * Math.min(100, rawDiscount)) / 100)
            : discountType === 'flat'
              ? round2(Math.min(subtotal, rawDiscount))
              : 0;

    const taxableBase = round2(Math.max(0, subtotal - discountAmount));
    const rate = Math.max(0, Number(taxRate) || 0);
    const taxAmount = round2((taxableBase * rate) / 100);
    const grandTotal = round2(taxableBase + taxAmount);

    // Both line-level and quote-level discounts count towards the discount
    // that the approval matrix sees.
    const totalDiscountAmount = round2(lineDiscountAmount + discountAmount);

    return {
        lines: computedLines,
        subtotal,
        grossSubtotal,
        lineDiscountAmount,
        discountAmount,
        totalDiscountAmount,
        taxableBase,
        taxRate: rate,
        taxAmount,
        grandTotal,
        effectiveDiscountPct:
            grossSubtotal > 0 ? round2((totalDiscountAmount / grossSubtotal) * 100) : 0,
    };
}

export function requiresApproval(totals, settings) {
    const discountThreshold = Number(settings?.approval?.discountThresholdPct ?? 15);
    const amountThreshold = Number(settings?.approval?.amountThreshold ?? 50000);

    return totals.effectiveDiscountPct > discountThreshold || totals.grandTotal > amountThreshold;
}

/**
 * Mirrors App\Services\PriceApprovalService: the lowest layer whose discount
 * and value ceilings both cover the quote. The server recalculates this on
 * save, so the banner is guidance, not enforcement.
 */
export function resolveApproval(totals, policy, paymentTermDays = 30) {
    const layers = [...(policy?.layers ?? [])].sort((a, b) => a.level - b.level);
    const myLevel = Number(policy?.my_level ?? 0);
    const term = Math.max(0, Number(paymentTermDays) || 0);

    if (layers.length === 0) {
        const fallbackTerm = Number(policy?.fallback?.payment_term_days ?? 30);
        const needsSignOff =
            requiresApproval(totals, {
                approval: {
                    discountThresholdPct: policy?.fallback?.discount_threshold_pct,
                    amountThreshold: policy?.fallback?.amount_threshold,
                },
            }) || term > fallbackTerm;

        return {
            requiresApproval: needsSignOff,
            requiredLevel: null,
            requiredLabel: needsSignOff ? 'Sales Manager' : null,
            myLevel,
            myDiscountLimit: policy?.my_discount_limit ?? null,
            myAmountLimit: policy?.my_amount_limit ?? null,
            myPaymentTermLimit: policy?.fallback?.payment_term_days ?? 30,
        };
    }

    const required =
        layers.find(
            (layer) =>
                totals.effectiveDiscountPct <= Number(layer.max_discount_pct) &&
                (layer.max_quote_amount === null || totals.grandTotal <= Number(layer.max_quote_amount)) &&
                (layer.max_payment_term_days === null || term <= Number(layer.max_payment_term_days)),
        ) ?? layers[layers.length - 1];

    return {
        requiresApproval: required.level > myLevel,
        requiredLevel: required.level,
        requiredLabel: required.label,
        myLevel,
        myDiscountLimit: policy?.my_discount_limit ?? null,
        myAmountLimit: policy?.my_amount_limit ?? null,
        myPaymentTermLimit: policy?.my_payment_term_limit ?? null,
    };
}
