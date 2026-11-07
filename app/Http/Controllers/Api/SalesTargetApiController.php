<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Models\SalesTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SalesTargetApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'opportunities.view');

        $targets = SalesTarget::query()
            ->when($request->filled('year'), fn ($query) => $query->where('year', $request->integer('year')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('brand_id'), fn ($query) => $query->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('account_id'), fn ($query) => $query->where('account_id', $request->integer('account_id')))
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->with(['user:id,name', 'brand:id,name', 'account:id,name', 'product:id,sku,name'])
            ->orderByDesc('year')
            ->orderBy('quarter')
            ->orderBy('month')
            ->paginate($this->perPage($request));

        return response()->json($targets);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'opportunities.manage');

        $target = SalesTarget::create($request->validate($this->rules()));

        return response()->json($target->load(['user:id,name', 'brand:id,name', 'account:id,name', 'product:id,sku,name']), 201);
    }

    public function show(Request $request, SalesTarget $salesTarget): JsonResponse
    {
        $this->guardPermission($request, 'opportunities.view');

        return response()->json($salesTarget->load(['user:id,name', 'brand:id,name', 'account:id,name', 'product:id,sku,name']));
    }

    public function update(Request $request, SalesTarget $salesTarget): JsonResponse
    {
        $this->guardPermission($request, 'opportunities.manage');

        $salesTarget->update($request->validate($this->rules(true)));

        return response()->json($salesTarget->refresh()->load(['user:id,name', 'brand:id,name']));
    }

    public function destroy(Request $request, SalesTarget $salesTarget): Response
    {
        $this->guardPermission($request, 'opportunities.manage');

        $salesTarget->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'year' => [$presence, 'integer', 'min:2000', 'max:2100'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'target_amount' => [$presence, 'numeric', 'min:0', 'max:999999999.99'],
        ];
    }
}
