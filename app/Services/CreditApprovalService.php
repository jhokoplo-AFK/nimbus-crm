<?php

namespace App\Services;

use App\Data\CreditDecision;
use App\Models\Account;
use App\Models\PriceApprovalLayer;

/**
 * Decides whether a customer's credit position blocks a quotation.
 *
 * Exposure is the outstanding balance plus the new quote. A configured credit
 * limit that would be breached, or any overdue balance, routes the quote up the
 * same approval ladder used for pricing; the exposed amount picks the lowest
 * layer whose credit ceiling still covers it. Approving the quotation approves
 * the credit too, and that decision is stored on the quote for the ERP export.
 */
class CreditApprovalService
{
    public function __construct(private readonly PriceApprovalService $prices) {}

    public function evaluate(Account $account, float $quoteTotal): CreditDecision
    {
        $limit = $account->credit_limit === null ? null : (float) $account->credit_limit;
        $balanceDue = (float) $account->balance_due;
        $overdue = (float) $account->overdue_balance;
        $exposure = round($balanceDue + $quoteTotal, 2);

        $overLimit = $limit !== null && $exposure > $limit;
        $isOverdue = $overdue > 0;

        if (! $overLimit && ! $isOverdue) {
            return new CreditDecision(
                requiresApproval: false,
                exposure: $exposure,
                creditLimit: $limit,
                balanceDue: $balanceDue,
                overdueBalance: $overdue,
            );
        }

        // A breach is never self-approved by the sales layer: even a small
        // overrun or an overdue invoice needs a manager to look at it.
        $level = (int) config('crm.credit.minimum_approval_level', 2);

        if ($overLimit) {
            $level = max($level, $this->levelForExposure($exposure));
        }

        return new CreditDecision(
            requiresApproval: true,
            requiredLevel: $level,
            reason: match (true) {
                $overLimit && $isOverdue => 'credit_limit_and_overdue',
                $overLimit => 'credit_limit',
                default => 'overdue',
            },
            exposure: $exposure,
            creditLimit: $limit,
            balanceDue: $balanceDue,
            overdueBalance: $overdue,
        );
    }

    private function levelForExposure(float $exposure): int
    {
        $layers = $this->prices->layers();

        if ($layers->isEmpty()) {
            return (int) config('crm.credit.minimum_approval_level', 2);
        }

        /** @var PriceApprovalLayer|null $match */
        $match = null;

        foreach ($layers as $layer) {
            if ($layer->max_credit_exposure === null || $exposure <= (float) $layer->max_credit_exposure) {
                $match = $layer;
                break;
            }
        }

        $match ??= $layers->last();

        return $match->level;
    }
}
