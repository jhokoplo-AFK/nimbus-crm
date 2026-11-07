<?php

namespace App\Http\Controllers\Api;

use App\Actions\ApproveQuotationAction;
use App\Actions\RejectQuotationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectQuotationRequest;
use App\Http\Requests\StoreQuotationRequest;
use App\Http\Requests\UpdateQuotationRequest;
use App\Models\Opportunity;
use App\Models\Pricebook;
use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QuotationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quotation::class);

        $quotations = Quotation::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('opportunity_id'), fn ($query) => $query->where('opportunity_id', $request->integer('opportunity_id')))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($q) => $q
                ->where('quote_number', 'like', '%'.$request->string('search')->value().'%')
                ->orWhereHas('opportunity', fn ($opportunity) => $opportunity->where('name', 'like', '%'.$request->string('search')->value().'%'))))
            ->with(['opportunity:id,name,account_id', 'opportunity.account:id,name', 'pricebook:id,name'])
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 25), 1), 100));

        return response()->json($quotations);
    }

    public function store(StoreQuotationRequest $request, QuotationService $service): JsonResponse
    {
        $data = $request->validated();

        $quotation = $service->create(
            Opportunity::query()->findOrFail($data['opportunity_id']),
            Pricebook::query()->findOrFail($data['pricebook_id']),
            $data,
            $request->user(),
        );

        return response()->json($quotation->load(['lineItems.product', 'opportunity.account', 'pricebook']), 201);
    }

    public function show(Request $request, Quotation $quotation): JsonResponse
    {
        $this->authorize('view', $quotation);

        return response()->json($quotation->load([
            'lineItems.product:id,sku,name',
            'opportunity.account:id,name',
            'pricebook:id,name',
            'approvedBy:id,name',
        ]));
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation, QuotationService $service): JsonResponse
    {
        $quotation = $service->update($quotation, $request->validated(), $request->user());

        return response()->json($quotation->load(['lineItems.product', 'opportunity.account', 'pricebook']));
    }

    public function destroy(Request $request, Quotation $quotation): Response
    {
        $this->authorize('update', $quotation);

        $quotation->delete();

        return response()->noContent();
    }

    public function approve(Request $request, Quotation $quotation, ApproveQuotationAction $action): JsonResponse
    {
        $this->authorize('approve', $quotation);

        $quotation = $action->execute($quotation, $request->user());

        return response()->json($quotation);
    }

    public function reject(RejectQuotationRequest $request, Quotation $quotation, RejectQuotationAction $action): JsonResponse
    {
        $quotation = $action->execute($quotation, $request->user(), $request->validated('reason'));

        return response()->json($quotation);
    }

    public function revise(Request $request, Quotation $quotation, QuotationService $service): JsonResponse
    {
        $this->authorize('revise', $quotation);

        $revision = $service->revise($quotation, $request->user());

        return response()->json($revision->load(['lineItems.product', 'opportunity.account', 'pricebook']), 201);
    }

    public function send(Request $request, Quotation $quotation, QuotationService $service): JsonResponse
    {
        $this->authorize('send', $quotation);

        return response()->json($service->markSent($quotation, $request->user()));
    }
}
