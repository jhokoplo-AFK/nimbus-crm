<?php

namespace App\Services;

use App\Actions\CalculateQuoteAction;
use App\Data\CreditDecision;
use App\Data\PriceApprovalDecision;
use App\Data\QuoteTotals;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\CreditStatus;
use App\Enums\DiscountType;
use App\Enums\QuotationStatus;
use App\Exceptions\PricebookNotAvailableException;
use App\Exceptions\QuotationStateException;
use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\Pricebook;
use App\Models\Quotation;
use App\Models\User;
use App\Support\DocumentNumberGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The quotation engine. Every price it stores is resolved from the selected
 * pricebook: client-supplied unit prices are deliberately ignored so a sales
 * rep cannot bypass the price list from the browser.
 */
class QuotationService
{
    public function __construct(
        private readonly CalculateQuoteAction $calculator,
        private readonly DocumentNumberGenerator $numbers,
        private readonly PriceApprovalService $approvals,
        private readonly CreditApprovalService $credit,
    ) {}

    /**
     * @param  array{lines: array<int, array{product_id: int, quantity: float|int|string, discount_pct?: float|int|string}>, discount_type?: string, discount_value?: float|int|string, tax_rate?: float|int|string, payment_term_days?: int|string|null, expiration_date?: string|null}  $payload
     */
    public function create(Opportunity $opportunity, Pricebook $pricebook, array $payload, ?User $actor = null): Quotation
    {
        return DB::transaction(function () use ($opportunity, $pricebook, $payload, $actor): Quotation {
            $pricebook = Pricebook::query()->whereKey($pricebook->getKey())->lockForUpdate()->firstOrFail();

            if (! $pricebook->is_active) {
                throw PricebookNotAvailableException::inactive();
            }

            $discountType = DiscountType::from($payload['discount_type'] ?? DiscountType::None->value);
            $discountValue = (float) ($payload['discount_value'] ?? 0);
            $taxRate = isset($payload['tax_rate']) ? (float) $payload['tax_rate'] : (float) config('crm.tax_rate', 11);

            $totals = $this->calculator->execute(
                $this->resolveLines($pricebook, $payload['lines']),
                $discountType,
                $discountValue,
                $taxRate,
            );

            $paymentTermDays = $this->paymentTermDays($payload['payment_term_days'] ?? null);

            // Price, payment term and customer credit are one gate: the quote
            // needs the highest level any of the three dimensions demands.
            $priceApproval = $this->approvals->evaluate($actor, $totals, $paymentTermDays);
            $credit = $this->credit->evaluate($opportunity->account()->firstOrFail(), $totals->grandTotal);
            [$status, $requiredLevel, $creditStatus, $creditLevel] = $this->route($priceApproval, $credit);

            // Only one quotation can be the primary quote of an opportunity.
            Quotation::query()
                ->where('opportunity_id', $opportunity->getKey())
                ->update(['is_primary' => false]);

            /** @var Quotation $quotation */
            $quotation = Quotation::create([
                'opportunity_id' => $opportunity->getKey(),
                'pricebook_id' => $pricebook->getKey(),
                'quote_number' => $this->numbers->next(Quotation::class, 'quote_number', (string) config('crm.documents.quote_prefix', 'Q')),
                'revision_number' => 1,
                'is_primary' => true,
                'status' => $status,
                'required_approval_level' => $requiredLevel,
                'subtotal' => $totals->subtotal,
                'line_discount_amount' => $totals->lineDiscountAmount,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $totals->discountAmount,
                'tax_rate' => $totals->taxRate,
                'tax_amount' => $totals->taxAmount,
                'grand_total' => $totals->grandTotal,
                'expiration_date' => $payload['expiration_date'] ?? now()->addDays(30)->toDateString(),
                'payment_term_days' => $paymentTermDays,
                'credit_status' => $creditStatus,
                'credit_required_level' => $creditLevel,
            ]);

            $this->persistLineItems($quotation, $totals->lines);
            $this->logActivity($quotation, $actor, "Quotation {$quotation->quote_number} created");

            return $quotation->load('lineItems.product', 'opportunity.account', 'pricebook');
        });
    }

    /**
     * @param  array{pricebook_id?: int, lines: array<int, array{product_id: int, quantity: float|int|string, discount_pct?: float|int|string}>, discount_type?: string, discount_value?: float|int|string, tax_rate?: float|int|string, payment_term_days?: int|string|null, expiration_date?: string|null}  $payload
     */
    public function update(Quotation $quotation, array $payload, ?User $actor = null): Quotation
    {
        return DB::transaction(function () use ($quotation, $payload, $actor): Quotation {
            /** @var Quotation $quotation */
            $quotation = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();

            if (! $quotation->isEditable()) {
                throw QuotationStateException::notEditable();
            }

            $pricebook = Pricebook::query()->findOrFail($payload['pricebook_id'] ?? $quotation->pricebook_id);

            if ($pricebook->getKey() !== $quotation->pricebook_id && ! $pricebook->is_active) {
                throw PricebookNotAvailableException::inactive();
            }

            $discountType = DiscountType::from($payload['discount_type'] ?? $quotation->discount_type->value);
            $discountValue = (float) ($payload['discount_value'] ?? $quotation->discount_value);
            $taxRate = isset($payload['tax_rate']) ? (float) $payload['tax_rate'] : (float) $quotation->tax_rate;

            $totals = $this->calculator->execute(
                $this->resolveLines($pricebook, $payload['lines']),
                $discountType,
                $discountValue,
                $taxRate,
            );

            $paymentTermDays = $this->paymentTermDays($payload['payment_term_days'] ?? $quotation->payment_term_days);

            // Re-evaluate routing on every edit: a quote that no longer
            // breaches policy returns to Draft, a harsher one escalates. Any
            // earlier credit sign-off is void because the exposure changed.
            $priceApproval = $this->approvals->evaluate($actor, $totals, $paymentTermDays);
            $credit = $this->credit->evaluate(
                $quotation->opportunity()->firstOrFail()->account()->firstOrFail(),
                $totals->grandTotal,
            );
            [$status, $requiredLevel, $creditStatus, $creditLevel] = $this->route($priceApproval, $credit);

            $quotation->forceFill([
                'pricebook_id' => $pricebook->getKey(),
                'status' => $status,
                'required_approval_level' => $requiredLevel,
                'subtotal' => $totals->subtotal,
                'line_discount_amount' => $totals->lineDiscountAmount,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $totals->discountAmount,
                'tax_rate' => $totals->taxRate,
                'tax_amount' => $totals->taxAmount,
                'grand_total' => $totals->grandTotal,
                'expiration_date' => $payload['expiration_date'] ?? $quotation->expiration_date,
                'payment_term_days' => $paymentTermDays,
                'credit_status' => $creditStatus,
                'credit_required_level' => $creditLevel,
                'credit_approved_by_id' => null,
                'credit_approved_at' => null,
            ])->save();

            $quotation->lineItems()->delete();
            $this->persistLineItems($quotation, $totals->lines);
            $this->logActivity($quotation, $actor, "Quotation {$quotation->quote_number} repriced");

            return $quotation->refresh()->load('lineItems.product', 'opportunity.account', 'pricebook');
        });
    }

    /**
     * Clones a quotation into the next revision: the previous version stops
     * being primary, line items are copied and all approvals are reset.
     */
    public function revise(Quotation $quotation, ?User $actor = null): Quotation
    {
        if (! $quotation->canBeRevised()) {
            throw QuotationStateException::notRevisable();
        }

        return DB::transaction(function () use ($quotation, $actor): Quotation {
            /** @var Quotation $quotation */
            $quotation = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();
            $quotation->load('lineItems', 'opportunity.account');

            $revision = $quotation->revision_number + 1;
            $baseNumber = preg_replace('/-R\d+$/', '', (string) $quotation->quote_number);

            Quotation::query()
                ->where('opportunity_id', $quotation->opportunity_id)
                ->update(['is_primary' => false]);

            /** @var Quotation $revisionQuote */
            $revisionQuote = $quotation->replicate([
                'quote_number',
                'revision_number',
                'is_primary',
                'status',
                'required_approval_level',
                'credit_status',
                'credit_required_level',
                'credit_approved_by_id',
                'credit_approved_at',
                'approved_by_id',
                'approved_at',
                'rejection_reason',
            ]);

            $revisionQuote->quote_number = "{$baseNumber}-R{$revision}";
            $revisionQuote->revision_number = $revision;
            $revisionQuote->is_primary = true;

            // A revision re-derives its approval routing from the current
            // matrix; a price cut may no longer need sign-off.
            $priceApproval = $this->approvals->evaluate($actor, new QuoteTotals(
                subtotal: (float) $revisionQuote->subtotal,
                discountAmount: (float) $revisionQuote->discount_amount,
                taxableBase: round((float) $revisionQuote->subtotal - (float) $revisionQuote->discount_amount, 2),
                taxRate: (float) $revisionQuote->tax_rate,
                taxAmount: (float) $revisionQuote->tax_amount,
                grandTotal: (float) $revisionQuote->grand_total,
                effectiveDiscountPct: $quotation->effectiveDiscountPct(),
                grossSubtotal: (float) $revisionQuote->subtotal + (float) $revisionQuote->line_discount_amount,
                lineDiscountAmount: (float) $revisionQuote->line_discount_amount,
                totalDiscountAmount: (float) $revisionQuote->line_discount_amount + (float) $revisionQuote->discount_amount,
            ), $revisionQuote->payment_term_days);

            $credit = $this->credit->evaluate(
                $revisionQuote->opportunity->account()->firstOrFail(),
                (float) $revisionQuote->grand_total,
            );

            [$status, $requiredLevel, $creditStatus, $creditLevel] = $this->route($priceApproval, $credit);

            $revisionQuote->status = $status;
            $revisionQuote->required_approval_level = $requiredLevel;
            $revisionQuote->credit_status = $creditStatus;
            $revisionQuote->credit_required_level = $creditLevel;
            $revisionQuote->credit_approved_by_id = null;
            $revisionQuote->credit_approved_at = null;
            $revisionQuote->approved_by_id = null;
            $revisionQuote->approved_at = null;
            $revisionQuote->rejection_reason = null;
            $revisionQuote->save();

            foreach ($quotation->lineItems as $line) {
                $revisionQuote->lineItems()->create($line->only([
                    'product_id', 'quantity', 'unit_price', 'discount_pct', 'total_price',
                ]));
            }

            $quotation->forceFill(['is_primary' => false])->save();
            $this->logActivity($quotation, $actor, "Quotation {$quotation->quote_number} revised to R{$revision}");

            return $revisionQuote->load('lineItems.product', 'opportunity.account', 'pricebook');
        });
    }

    /**
     * Marks an approved quotation as sent to the customer.
     */
    public function markSent(Quotation $quotation, ?User $actor = null): Quotation
    {
        if ($quotation->status !== QuotationStatus::Approved) {
            throw QuotationStateException::notApproved();
        }

        $quotation->forceFill(['status' => QuotationStatus::Sent])->save();
        $this->logActivity($quotation, $actor, "Quotation {$quotation->quote_number} sent to customer");

        return $quotation->refresh();
    }

    /**
     * One approval gate: price, payment term and credit each report a required
     * level, and the quote carries the highest of them.
     *
     * @return array{0: QuotationStatus, 1: int|null, 2: CreditStatus, 3: int|null}
     */
    private function route(PriceApprovalDecision $price, CreditDecision $credit): array
    {
        $requiresApproval = $price->requiresApproval || $credit->requiresApproval;

        return [
            $requiresApproval ? QuotationStatus::InReview : QuotationStatus::Draft,
            $requiresApproval ? max($price->requiredLevel ?? 0, $credit->requiredLevel ?? 0) : null,
            $credit->requiresApproval ? CreditStatus::Pending : CreditStatus::NotRequired,
            $credit->requiresApproval ? $credit->requiredLevel : null,
        ];
    }

    private function paymentTermDays(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 30;
        }

        return min(365, max(0, (int) $value));
    }

    /**
     * Resolves authoritative unit prices from the selected pricebook.
     *
     * @param  array<int, array{product_id: int, quantity: float|int|string, discount_pct?: float|int|string}>  $lines
     * @return array<int, array{product_id: int, quantity: float, unit_price: float, discount_pct: float}>
     */
    private function resolveLines(Pricebook $pricebook, array $lines): array
    {
        if ($lines === []) {
            throw QuotationStateException::empty();
        }

        /** @var Collection<int, int> $productIds */
        $productIds = collect($lines)
            ->map(static fn (array $line): int => (int) $line['product_id'])
            ->unique()
            ->values();

        $entries = $pricebook->entries()
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $resolved = [];

        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $entry = $entries->get($productId);

            if ($entry === null) {
                throw PricebookNotAvailableException::missingPrice($productId);
            }

            $resolved[] = [
                'product_id' => $productId,
                'quantity' => max(0.01, (float) $line['quantity']),
                'unit_price' => (float) $entry->list_price,
                'discount_pct' => min(100.0, max(0.0, (float) ($line['discount_pct'] ?? 0))),
            ];
        }

        return $resolved;
    }

    /**
     * @param  array<int, array{product_id: int, quantity: float, unit_price: float, discount_pct: float, total_price: float}>  $lines
     */
    private function persistLineItems(Quotation $quotation, array $lines): void
    {
        foreach ($lines as $line) {
            $quotation->lineItems()->create($line);
        }
    }

    private function logActivity(Quotation $quotation, ?User $actor, string $subject): void
    {
        if ($actor === null) {
            return;
        }

        Activity::create([
            'subject' => $subject,
            'type' => ActivityType::Task,
            'status' => ActivityStatus::Completed,
            'due_date' => now()->toDateString(),
            'relatable_type' => $quotation->opportunity->getMorphClass(),
            'relatable_id' => $quotation->opportunity_id,
            'user_id' => $actor->getKey(),
        ]);
    }
}
