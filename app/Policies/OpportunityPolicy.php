<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('opportunities.view');
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->can('opportunities.view');
    }

    public function create(User $user): bool
    {
        return $user->can('opportunities.manage');
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->can('opportunities.manage');
    }

    /**
     * Stage moves are the Kanban board's write path.
     */
    public function updateStage(User $user, Opportunity $opportunity): bool
    {
        return $user->can('opportunities.manage');
    }
}
