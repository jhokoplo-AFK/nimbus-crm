<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityRequest;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ActivityApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $activities = Activity::query()
            ->when($request->filled('relatable_type'), fn ($query) => $query->where('relatable_type', $request->string('relatable_type')->value()))
            ->when($request->filled('relatable_id'), fn ($query) => $query->where('relatable_id', $request->integer('relatable_id')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->boolean('open'), fn ($query) => $query->open())
            ->with('user:id,name')
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($activities);
    }

    public function store(StoreActivityRequest $request): JsonResponse
    {
        $data = $request->validated();

        $modelClass = Relation::getMorphedModel($data['relatable_type']);
        abort_if($modelClass === null, 422, 'Unknown related record type.');

        $related = $modelClass::query()->findOrFail($data['relatable_id']);

        $activity = Activity::create([
            'subject' => $data['subject'],
            'type' => $data['type'],
            'status' => $data['status'] ?? ActivityStatus::Open->value,
            'due_date' => $data['due_date'] ?? null,
            'relatable_type' => $related->getMorphClass(),
            'relatable_id' => $related->getKey(),
            'user_id' => $request->user()->getKey(),
        ]);

        return response()->json($activity->load('user:id,name'), 201);
    }

    public function show(Request $request, Activity $activity): JsonResponse
    {
        return response()->json($activity->load('user:id,name', 'relatable'));
    }

    public function update(Request $request, Activity $activity): JsonResponse
    {
        abort_unless(
            $activity->user_id === $request->user()->getKey() || $request->user()->can('opportunities.manage'),
            403,
        );

        $activity->update($request->validate([
            'subject' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(ActivityType::class)],
            'status' => ['sometimes', Rule::enum(ActivityStatus::class)],
            'due_date' => ['nullable', 'date'],
        ]));

        return response()->json($activity->refresh()->load('user:id,name'));
    }

    public function complete(Request $request, Activity $activity): JsonResponse
    {
        abort_unless(
            $activity->user_id === $request->user()->getKey() || $request->user()->can('opportunities.manage'),
            403,
        );

        $activity->complete();

        return response()->json($activity->refresh());
    }

    public function destroy(Request $request, Activity $activity): Response
    {
        abort_unless(
            $activity->user_id === $request->user()->getKey() || $request->user()->can('opportunities.manage'),
            403,
        );

        $activity->delete();

        return response()->noContent();
    }
}
