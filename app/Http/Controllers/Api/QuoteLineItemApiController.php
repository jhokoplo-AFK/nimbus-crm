<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Models\QuoteLineItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QuoteLineItemApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quotation::class);

        $items = QuoteLineItem::query()
            ->when($request->filled('quotation_id'), fn ($query) => $query->where('quotation_id', $request->integer('quotation_id')))
            ->with(['product:id,sku,name', 'quotation:id,quote_number,status'])
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 100));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Quotation::class);

        $data = $request->validate($this->rules());

        $item = QuoteLineItem::create([
            ...$data,
            'total_price' => $this->lineTotal(
                (float) $data['quantity'],
                (float) $data['unit_price'],
                (float) ($data['discount_pct'] ?? 0),
            ),
        ]);

        return response()->json($item->load('product:id,sku,name'), 201);
    }

    public function show(Request $request, QuoteLineItem $quoteLineItem): JsonResponse
    {
        $this->authorize('view', $quoteLineItem->quotation);

        return response()->json($quoteLineItem->load(['product:id,sku,name', 'quotation:id,quote_number,status']));
    }

    public function update(Request $request, QuoteLineItem $quoteLineItem): JsonResponse
    {
        $this->authorize('update', $quoteLineItem->quotation);

        $data = $request->validate($this->rules(true));
        $quantity = (float) ($data['quantity'] ?? $quoteLineItem->quantity);
        $unitPrice = (float) ($data['unit_price'] ?? $quoteLineItem->unit_price);
        $discount = (float) ($data['discount_pct'] ?? $quoteLineItem->discount_pct);

        $quoteLineItem->update([
            ...$data,
            'total_price' => $this->lineTotal($quantity, $unitPrice, $discount),
        ]);

        return response()->json($quoteLineItem->refresh()->load('product:id,sku,name'));
    }

    public function destroy(Request $request, QuoteLineItem $quoteLineItem): Response
    {
        $this->authorize('update', $quoteLineItem->quotation);

        $quoteLineItem->delete();

        return response()->noContent();
    }

    private function lineTotal(float $quantity, float $unitPrice, float $discountPct): float
    {
        $gross = round(max(0, $quantity) * max(0, $unitPrice), 2);
        $discount = round($gross * (min(100, max(0, $discountPct)) / 100), 2);

        return round($gross - $discount, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'quotation_id' => [$presence, 'integer', 'exists:quotations,id'],
            'product_id' => [$presence, 'integer', 'exists:products,id'],
            'quantity' => [$presence, 'numeric', 'min:0.01', 'max:100000'],
            'unit_price' => [$presence, 'numeric', 'min:0', 'max:999999999.99'],
            'discount_pct' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
