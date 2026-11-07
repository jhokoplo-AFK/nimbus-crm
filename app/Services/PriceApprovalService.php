<?php

namespace App\Services;

use App\Data\PriceApprovalDecision;
use App\Data\QuoteTotals;
use App\Models\PriceApprovalLayer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves which authority layer may sign a quote.
 *
 * The matrix is stored in `price_approval_layers`; each layer declares the
 * maximum discount and grand total it can approve. A quote is routed to the
 * lowest layer that covers both figures, and a user may only approve when their
 * own layer is at or above that requirement. When no matrix has been seeded yet
 * the config thresholds are used as a safe fallback, so a fresh install cannot
 * silently approve everything.
 */
class PriceApprovalService
{
    /**
     * @return Collection<int, PriceApprovalLayer>
     */
    public function layers(): Collection
    {
        return PriceApprovalLayer::query()->active()->ordered()->get();
    }

    /**
     * Highest layer held by the user's roles. Users without a mapped role are
     * treated as the entry layer instead of being granted unlimited authority.
     */
    public function layerFor(?User $user): ?PriceApprovalLayer
    {
        $layers = $this->layers();

        if ($layers->isEmpty()) {
            return null;
        }

        if ($user === null) {
            return $layers->first();
        }

        $roles = $user->getRoleNames()->all();

        if ($roles === []) {
            return $layers->first();
        }

        return $layers
            ->filter(fn (PriceApprovalLayer $layer): bool => collect($roles)
                ->contains(fn (string $role): bool => $layer->appliesToRole($role)))
            ->sortByDesc('level')
            ->first() ?? $layers->first();
    }

    public function levelFor(?User $user): int
    {
        return $this->layerFor($user)?->level ?? 0;
    }

    public function evaluate(?User $user, QuoteTotals $totals, ?int $paymentTermDays = null): PriceApprovalDecision
    {
        $layers = $this->layers();

        if ($layers->isEmpty()) {
            return $this->fallbackDecision($totals, $paymentTermDays);
        }

        $submitter = $this->layerFor($user);
        $required = $layers->first(
            fn (PriceApprovalLayer $layer): bool => $layer->covers($totals->effectiveDiscountPct, $totals->grandTotal, $paymentTermDays)
        ) ?? $layers->last();

        $submitterLevel = $submitter?->level ?? 0;

        if ($required->level <= $submitterLevel) {
            return new PriceApprovalDecision(
                requiresApproval: false,
                submitterLevel: $submitterLevel,
                reason: 'within_limit',
            );
        }

        return new PriceApprovalDecision(
            requiresApproval: true,
            requiredLevel: $required->level,
            requiredRole: $required->label,
            submitterLevel: $submitterLevel,
            reason: $this->reason($totals, $submitter, $paymentTermDays),
        );
    }

    /**
     * Null means the quote predates the matrix (or was priced before one was
     * configured), so the existing `quotations.approve` permission decides.
     */
    public function canApprove(User $user, ?int $requiredLevel): bool
    {
        if ($requiredLevel === null) {
            return true;
        }

        $layer = $this->layerFor($user);

        return $layer !== null && $layer->level >= $requiredLevel;
    }

    public function requiredRoleLabel(?int $level): ?string
    {
        if ($level === null) {
            return null;
        }

        return $this->layers()->firstWhere('level', $level)?->label;
    }

    /**
     * Payload for the quotation builder: the matrix, the actor's own limits and
     * the config fallback for installs that have not seeded layers.
     *
     * @return array<string, mixed>
     */
    public function policyFor(?User $user): array
    {
        $mine = $this->layerFor($user);

        return [
            'layers' => $this->layers()->map(fn (PriceApprovalLayer $layer): array => [
                'level' => $layer->level,
                'label' => $layer->label,
                'role_name' => $layer->role_name,
                'max_discount_pct' => (float) $layer->max_discount_pct,
                'max_quote_amount' => $layer->max_quote_amount === null ? null : (float) $layer->max_quote_amount,
                'max_payment_term_days' => $layer->max_payment_term_days,
                'max_credit_exposure' => $layer->max_credit_exposure === null ? null : (float) $layer->max_credit_exposure,
            ])->values()->all(),
            'my_level' => $mine?->level ?? 0,
            'my_label' => $mine?->label,
            'my_discount_limit' => $mine === null ? null : (float) $mine->max_discount_pct,
            'my_amount_limit' => $mine === null || $mine->max_quote_amount === null
                ? null
                : (float) $mine->max_quote_amount,
            'my_payment_term_limit' => $mine?->max_payment_term_days,
            'my_credit_limit' => $mine === null || $mine->max_credit_exposure === null
                ? null
                : (float) $mine->max_credit_exposure,
            'fallback' => [
                'discount_threshold_pct' => (float) config('crm.approval.discount_threshold_pct', 15),
                'amount_threshold' => (float) config('crm.approval.amount_threshold', 50000),
                'payment_term_days' => (int) config('crm.approval.payment_term_days', 30),
            ],
        ];
    }

    private function fallbackDecision(QuoteTotals $totals, ?int $paymentTermDays = null): PriceApprovalDecision
    {
        $termLimit = (int) config('crm.approval.payment_term_days', 30);

        $requiresApproval = $totals->effectiveDiscountPct > (float) config('crm.approval.discount_threshold_pct', 15)
            || $totals->grandTotal > (float) config('crm.approval.amount_threshold', 50000)
            || ($paymentTermDays !== null && $paymentTermDays > $termLimit);

        return new PriceApprovalDecision(
            requiresApproval: $requiresApproval,
            reason: $requiresApproval ? 'fallback_policy' : 'within_limit',
        );
    }

    private function reason(QuoteTotals $totals, ?PriceApprovalLayer $submitter, ?int $paymentTermDays = null): string
    {
        $maxDiscount = $submitter === null ? 0.0 : (float) $submitter->max_discount_pct;
        $maxAmount = $submitter === null || $submitter->max_quote_amount === null
            ? INF
            : (float) $submitter->max_quote_amount;
        $maxTerm = $submitter?->max_payment_term_days;

        $breaches = array_values(array_filter([
            $totals->effectiveDiscountPct > $maxDiscount ? 'discount' : null,
            $totals->grandTotal > $maxAmount ? 'amount' : null,
            $paymentTermDays !== null && $maxTerm !== null && $paymentTermDays > $maxTerm ? 'payment_term' : null,
        ]));

        return $breaches === [] ? 'requires_higher_authority' : implode('_and_', $breaches);
    }
}
