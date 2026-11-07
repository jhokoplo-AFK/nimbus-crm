<?php

namespace App\Actions;

use App\Enums\CreditStatus;
use App\Enums\OpportunityStage;
use App\Enums\QuotationStatus;
use App\Exceptions\QuotationStateException;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveQuotationAction
{
    /**
     * Approves a quotation that is awaiting sign-off and moves the parent
     * opportunity out of the early stages if it is still there.
     */
    public function execute(Quotation $quotation, User $approver): Quotation
    {
        return DB::transaction(function () use ($quotation, $approver): Quotation {
            /** @var Quotation $quotation */
            $quotation = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();

            if ($quotation->status !== QuotationStatus::InReview) {
                throw QuotationStateException::notAwaitingApproval();
            }

            $attributes = [
                'status' => QuotationStatus::Approved,
                'approved_by_id' => $approver->getKey(),
                'approved_at' => now(),
                'rejection_reason' => null,
            ];

            // One signature clears both gates: the approver already proved
            // their level covers price, payment term and credit exposure.
            if ($quotation->credit_status === CreditStatus::Pending) {
                $attributes['credit_status'] = CreditStatus::Approved;
                $attributes['credit_approved_by_id'] = $approver->getKey();
                $attributes['credit_approved_at'] = now();
            }

            $quotation->forceFill($attributes)->save();

            $opportunity = $quotation->opportunity;

            if ($opportunity !== null && in_array($opportunity->stage, [OpportunityStage::Prospecting, OpportunityStage::Qualification], true)) {
                $opportunity->forceFill([
                    'stage' => OpportunityStage::Proposal,
                    'probability_pct' => OpportunityStage::Proposal->defaultProbability(),
                ])->save();
            }

            return $quotation->refresh()->load('lineItems.product', 'opportunity.account', 'pricebook', 'approvedBy');
        });
    }
}
