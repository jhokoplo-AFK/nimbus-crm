<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;
use App\Services\PriceApprovalService;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotations.view');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('quotations.create');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.update') && $quotation->isEditable();
    }

    public function revise(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.create') && $quotation->canBeRevised();
    }

    /**
     * Separation of duties: reps raise quotes, managers approve them, and a
     * quote priced outside the approver's own layer must escalate further up
     * the matrix (Manager -> Regional -> General Manager -> Director).
     */
    public function approve(User $user, Quotation $quotation): bool
    {
        if (! $user->can('quotations.approve') || ! $quotation->isAwaitingApproval()) {
            return false;
        }

        return app(PriceApprovalService::class)->canApprove($user, $quotation->required_approval_level);
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $this->approve($user, $quotation);
    }

    public function send(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.update');
    }
}
