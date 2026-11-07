<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Models\PriceApprovalLayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets administrators tune the price approval matrix without a deployment.
 */
class PriceApprovalLayerApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'approvals.manage');

        return response()->json([
            'data' => PriceApprovalLayer::query()->ordered()->get(),
        ]);
    }

    public function show(Request $request, PriceApprovalLayer $priceApprovalLayer): JsonResponse
    {
        $this->guardPermission($request, 'approvals.manage');

        return response()->json($priceApprovalLayer);
    }

    public function update(Request $request, PriceApprovalLayer $priceApprovalLayer): JsonResponse
    {
        $this->guardPermission($request, 'approvals.manage');

        $priceApprovalLayer->update($request->validate([
            'max_discount_pct' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'max_quote_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_payment_term_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'max_credit_exposure' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'can_approve' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return response()->json($priceApprovalLayer->refresh());
    }
}
