<?php

namespace App\Models\Concerns;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Any record that can carry a sales activity timeline (accounts, contacts,
 * leads, opportunities) uses this concern.
 */
trait HasActivities
{
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'relatable');
    }

    public function openActivities(): MorphMany
    {
        return $this->activities()
            ->where('status', ActivityStatus::Open->value)
            ->orderBy('due_date');
    }
}
