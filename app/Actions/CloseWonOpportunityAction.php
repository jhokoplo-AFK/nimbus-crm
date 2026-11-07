<?php

namespace App\Actions;

use App\Enums\OpportunityStage;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Support\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Closed-Won automation: lock the opportunity at 100% probability, hand the
 * commercial terms to the ERP pipeline as a Draft Sales Order, and copy the
 * winning quote lines onto the order so revenue can be reported by product
 * and brand.
 */
class CloseWonOpportunityAction
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    public function execute(Opportunity $opportunity): SalesOrder
    {
        return DB::transaction(function () use ($opportunity): SalesOrder {
            /** @var Opportunity $opportunity */
            $opportunity = Opportunity::query()->whereKey($opportunity->getKey())->lockForUpdate()->firstOrFail();

            $opportunity->forceFill([
                'stage' => OpportunityStage::ClosedWon,
                'probability_pct' => 100,
                'loss_reason' => null,
            ])->save();

            // Idempotency: re-saving a won opportunity must not create a second
            // order. This also makes the action safe to replay from a queue.
            $existing = $opportunity->salesOrders()->latest('id')->first();

            if ($existing !== null) {
                return $existing;
            }

            $quotation = $this->winningQuotation($opportunity);

            /** @var SalesOrder $order */
            $order = SalesOrder::create([
                'contract_id' => $opportunity->contracts()->latest('id')->value('id'),
                'account_id' => $opportunity->account_id,
                'opportunity_id' => $opportunity->id,
                'user_id' => $opportunity->owner_id,
                'order_number' => $this->numbers->next(
                    SalesOrder::class,
                    'order_number',
                    (string) config('crm.documents.order_prefix', 'SO'),
                ),
                'order_date' => now()->toDateString(),
                'subtotal' => $quotation?->subtotal ?? $opportunity->amount,
                'total_amount' => $quotation?->grand_total ?? $opportunity->amount,
                'status' => SalesOrderStatus::Draft,
            ]);

            $this->copyLineItems($order, $quotation, $opportunity);

            return $order->load('items.product');
        });
    }

    /**
     * Prefer the latest approved or sent quote; fall back to the primary quote
     * so imported or manually closed deals still produce reportable lines.
     */
    private function winningQuotation(Opportunity $opportunity): ?Quotation
    {
        return $opportunity->quotations()
            ->whereIn('status', [QuotationStatus::Approved->value, QuotationStatus::Sent->value])
            ->orderByDesc('revision_number')
            ->first()
            ?? $opportunity->quotations()
                ->orderByDesc('is_primary')
                ->orderByDesc('revision_number')
                ->first();
    }

    private function copyLineItems(SalesOrder $order, ?Quotation $quotation, Opportunity $opportunity): void
    {
        if ($quotation !== null && $quotation->lineItems()->exists()) {
            $quotation->lineItems->each(function ($line) use ($order): void {
                $order->items()->create([
                    'product_id' => $line->product_id,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'total_price' => $line->total_price,
                ]);
            });

            return;
        }

        // No priced quote: keep the revenue on the order as an unassigned line
        // instead of silently dropping it from product/brand reporting.
        $order->items()->create([
            'product_id' => null,
            'quantity' => 1,
            'unit_price' => $order->subtotal,
            'total_price' => $order->subtotal,
        ]);
    }
}
