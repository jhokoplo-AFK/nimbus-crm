<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContractStatus;
use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ContractApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'contracts.view');

        $contracts = Contract::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('account_id'), fn ($query) => $query->where('account_id', $request->integer('account_id')))
            ->with(['account:id,name', 'opportunity:id,name'])
            ->orderByDesc('start_date')
            ->paginate($this->perPage($request));

        return response()->json($contracts);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'contracts.view');

        $contract = Contract::create($request->validate($this->rules()));

        return response()->json($contract->load(['account:id,name', 'opportunity:id,name']), 201);
    }

    public function show(Request $request, Contract $contract): JsonResponse
    {
        $this->guardPermission($request, 'contracts.view');

        return response()->json($contract->load(['account', 'opportunity', 'salesOrders']));
    }

    public function update(Request $request, Contract $contract): JsonResponse
    {
        $this->guardPermission($request, 'contracts.view');

        $contract->update($request->validate($this->rules($contract)));

        return response()->json($contract->refresh());
    }

    public function destroy(Request $request, Contract $contract): Response
    {
        $this->guardPermission($request, 'contracts.view');

        $contract->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?Contract $contract = null): array
    {
        $presence = $contract === null ? 'required' : 'sometimes';

        return [
            'account_id' => [$presence, 'integer', 'exists:accounts,id'],
            'opportunity_id' => [$presence, 'integer', 'exists:opportunities,id'],
            'contract_number' => [$presence, 'string', 'max:100', Rule::unique('contracts', 'contract_number')->ignore($contract)],
            'start_date' => [$presence, 'date'],
            'end_date' => [$presence, 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::enum(ContractStatus::class)],
            'terms_and_conditions' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
