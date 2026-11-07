<?php

namespace App\Actions;

use App\Enums\CreditStatus;
use App\Enums\QuotationStatus;
use App\Exceptions\QuotationStateException;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectQuotationAction
{
    public function execute(Quotation $quotation, User $approver, string $reason): Quotation
    {
        return DB::transaction(function () use ($quotation, $approver, $reason): Quotation {
            /** @var Quotation $quotation */
            $quotation = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();

            if ($quotation->status !== QuotationStatus::InReview) {
                throw QuotationStateException::notAwaitingApproval();
            }

            $attributes = [
                'status' => QuotationStatus::Rejected,
                'approved_by_id' => $approver->getKey(),
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ];

            if ($quotation->credit_status === CreditStatus::Pending) {
                $attributes['credit_status'] = CreditStatus::Rejected;
            }

            $quotation->forceFill($attributes)->save();

            return $quotation->refresh()->load('lineItems.product', 'opportunity.account', 'pricebook', 'approvedBy');
        });
    }
}
