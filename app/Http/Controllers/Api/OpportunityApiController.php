<?php

namespace App\Http\Controllers\Api;

use App\Actions\ChangeOpportunityStageAction;
use App\Enums\OpportunityStage;
use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOpportunityRequest;
use App\Http\Requests\UpdateOpportunityStageRequest;
use App\Models\Opportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class OpportunityApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Opportunity::class);

        $opportunities = Opportunity::query()
            ->search($request->string('search')->value())
            ->when($request->filled('stage'), fn ($query) => $query->where('stage', $request->string('stage')->value()))
            ->when($request->filled('account_id'), fn ($query) => $query->where('account_id', $request->integer('account_id')))
            ->when($request->filled('owner_id'), fn ($query) => $query->where('owner_id', $request->integer('owner_id')))
            ->when($request->boolean('open'), fn ($query) => $query->open())
            ->with(['account:id,name', 'primaryContact:id,first_name,last_name', 'owner:id,name'])
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($opportunities);
    }

    public function store(StoreOpportunityRequest $request): JsonResponse
    {
        $data = $request->validated();
        $stage = OpportunityStage::from($data['stage']);

        $opportunity = Opportunity::create([
            ...$data,
            'owner_id' => $data['owner_id'] ?? $request->user()->getKey(),
            'probability_pct' => $data['probability_pct'] ?? $stage->defaultProbability(),
        ]);

        return response()->json($opportunity->load(['account:id,name', 'owner:id,name']), 201);
    }

    public function show(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorize('view', $opportunity);

        return response()->json($opportunity->load([
            'account:id,name,industry',
            'primaryContact',
            'owner:id,name',
            'quotations',
            'contracts',
            'salesOrders',
        ]));
    }

    public function update(Request $request, Opportunity $opportunity, ChangeOpportunityStageAction $stageAction): JsonResponse
    {
        $this->authorize('update', $opportunity);

        $data = $request->validate([
            'account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'primary_contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'stage' => ['sometimes', Rule::enum(OpportunityStage::class)],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:999999999.99'],
            'probability_pct' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'close_date' => ['nullable', 'date'],
            'loss_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($data['stage'])) {
            $stageAction->execute(
                $opportunity,
                OpportunityStage::from($data['stage']),
                $data['loss_reason'] ?? null,
            );

            $data = collect($data)->except(['stage', 'loss_reason'])->all();
        }

        if ($data !== []) {
            $opportunity->update($data);
        }

        return response()->json($opportunity->refresh()->load(['account:id,name', 'owner:id,name']));
    }

    public function updateStage(
        UpdateOpportunityStageRequest $request,
        Opportunity $opportunity,
        ChangeOpportunityStageAction $action,
    ): JsonResponse {
        $validated = $request->validated();

        $opportunity = $action->execute(
            $opportunity,
            OpportunityStage::from($validated['stage']),
            $validated['loss_reason'] ?? null,
        );

        return response()->json($opportunity->load(['account:id,name', 'owner:id,name', 'salesOrders']));
    }

    public function destroy(Request $request, Opportunity $opportunity): Response
    {
        $this->authorize('update', $opportunity);

        $opportunity->delete();

        return response()->noContent();
    }
}
