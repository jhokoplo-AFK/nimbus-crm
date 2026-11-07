<?php

namespace App\Actions;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for opportunity stage changes. Shared by the Kanban
 * board, the opportunity detail page and the REST API so probability, loss
 * reasons and the Closed-Won automation behave identically everywhere.
 */
class ChangeOpportunityStageAction
{
    public function execute(Opportunity $opportunity, OpportunityStage $stage, ?string $lossReason = null): Opportunity
    {
        return DB::transaction(function () use ($opportunity, $stage, $lossReason): Opportunity {
            /** @var Opportunity $opportunity */
            $opportunity = Opportunity::query()->whereKey($opportunity->getKey())->lockForUpdate()->firstOrFail();

            $opportunity->fill([
                'stage' => $stage,
                'probability_pct' => $stage->isWon() ? 100 : $stage->defaultProbability(),
                'loss_reason' => $stage->isLost() ? $lossReason : null,
            ]);

            // Saving triggers OpportunityObserver, which runs the Closed-Won
            // automation (probability lock + Draft Sales Order + line items).
            $opportunity->save();

            return $opportunity->refresh();
        });
    }
}
