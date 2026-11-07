<?php

namespace App\Http\Controllers\Api;

use App\Actions\ConvertLeadAction;
use App\Enums\LeadStatus;
use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConvertLeadRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class LeadApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $leads = Lead::query()
            ->search($request->string('search')->value())
            ->withStatus($request->string('status')->value() ?: null)
            ->when($request->filled('source'), fn ($query) => $query->where('lead_source', $request->string('source')->value()))
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($leads);
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = Lead::create($request->validated());

        return response()->json($lead, 201);
    }

    public function show(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        return response()->json($lead->load(['convertedAccount', 'convertedContact', 'convertedOpportunity']));
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        abort_if($lead->isLocked(), 403, 'Converted leads are read only.');

        $lead->update($request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'lead_source' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::enum(LeadStatus::class), Rule::notIn([LeadStatus::Converted->value])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]));

        return response()->json($lead->refresh());
    }

    public function destroy(Request $request, Lead $lead): Response
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return response()->noContent();
    }

    public function convert(ConvertLeadRequest $request, Lead $lead, ConvertLeadAction $action): JsonResponse
    {
        $result = $action->execute($lead, $request->validated(), $request->user());

        return response()->json([
            'data' => [
                'account' => $result->account,
                'contact' => $result->contact,
                'opportunity' => $result->opportunity,
                'lead' => $lead->refresh(),
            ],
        ], 201);
    }

    /**
     * Exposed so integrations can filter by status without knowing the enum.
     */
    public function statuses(): JsonResponse
    {
        return response()->json(['data' => LeadStatus::options()]);
    }
}
