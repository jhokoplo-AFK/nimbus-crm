<?php

namespace App\Http\Controllers;

use App\Enums\ActivityStatus;
use App\Http\Requests\StoreActivityRequest;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function store(StoreActivityRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $modelClass = Relation::getMorphedModel($data['relatable_type']);
        abort_if($modelClass === null, 422, 'Unknown related record type.');

        $related = $modelClass::query()->findOrFail($data['relatable_id']);

        Activity::create([
            'subject' => $data['subject'],
            'type' => $data['type'],
            'status' => $data['status'] ?? ActivityStatus::Open->value,
            'due_date' => $data['due_date'] ?? null,
            'relatable_type' => $related->getMorphClass(),
            'relatable_id' => $related->getKey(),
            'user_id' => $request->user()->getKey(),
        ]);

        return back()->with('success', 'Activity logged.');
    }

    public function complete(Request $request, Activity $activity): RedirectResponse
    {
        abort_unless(
            $activity->user_id === $request->user()->getKey()
                || $request->user()->can('opportunities.manage'),
            403,
        );

        $activity->complete();

        return back()->with('success', 'Activity completed.');
    }
}
