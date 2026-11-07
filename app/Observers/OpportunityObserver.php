<?php

namespace App\Observers;

use App\Actions\CloseWonOpportunityAction;
use App\Enums\OpportunityStage;
use App\Models\Opportunity;

class OpportunityObserver
{
    public function __construct(private readonly CloseWonOpportunityAction $closeWon) {}

    /**
     * Every stage transition into Closed Won is funnelled through the action so
     * the ERP hand-off happens no matter where the change came from (Kanban,
     * API, import or seeder).
     */
    public function updated(Opportunity $opportunity): void
    {
        if (! $opportunity->wasChanged('stage')) {
            return;
        }

        if ($opportunity->stage === OpportunityStage::ClosedWon) {
            $this->closeWon->execute($opportunity);
        }
    }

    public function created(Opportunity $opportunity): void
    {
        if ($opportunity->stage === OpportunityStage::ClosedWon) {
            $this->closeWon->execute($opportunity);
        }
    }
}
