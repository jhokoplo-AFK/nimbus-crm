<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->can('leads.view');
    }

    public function create(User $user): bool
    {
        return $user->can('leads.create');
    }

    /**
     * Converted leads are immutable: the conversion created the system of
     * record, so the lead becomes a read-only audit artifact.
     */
    public function update(User $user, Lead $lead): bool
    {
        return $user->can('leads.update') && ! $lead->isLocked();
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->can('leads.delete') && ! $lead->isLocked();
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $user->can('leads.convert')
            && ! $lead->isConverted()
            && $lead->status->isConvertible();
    }
}
