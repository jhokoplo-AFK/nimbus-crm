<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Models\SalesOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SalesOrderItemApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'orders.view');

        $items = SalesOrderItem::query()
            ->when($request->filled('sales_order_id'), fn ($query) => $query->where('sales_order_id', $request->integer('sales_order_id')))
            ->with(['product:id,sku,name,brand_id', 'product.brand:id,name', 'salesOrder:id,order_number,status'])
            ->paginate($this->perPage($request));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'orders.view');

        $data = $request->validate($this->rules());

        $item = SalesOrderItem::create([
            ...$data,
            'total_price' => $this->lineTotal(
                (float) $data['quantity'],
                (float) $data['unit_price'],
            ),
        ]);

        return response()->json($item->load('product:id,sku,name'), 201);
    }

    public function show(Request $request, SalesOrderItem $salesOrderItem): JsonResponse
    {
        $this->guardPermission($request, 'orders.view');

        return response()->json($salesOrderItem->load(['product:id,sku,name', 'salesOrder:id,order_number,status']));
    }

    public function update(Request $request, SalesOrderItem $salesOrderItem): JsonResponse
    {
        $this->guardPermission($request, 'orders.view');

        $data = $request->validate($this->rules(true));
        $quantity = (float) ($data['quantity'] ?? $salesOrderItem->quantity);
        $unitPrice = (float) ($data['unit_price'] ?? $salesOrderItem->unit_price);

        $salesOrderItem->update([
            ...$data,
            'total_price' => $this->lineTotal($quantity, $unitPrice),
        ]);

        return response()->json($salesOrderItem->refresh()->load('product:id,sku,name'));
    }

    public function destroy(Request $request, SalesOrderItem $salesOrderItem): Response
    {
        $this->guardPermission($request, 'orders.view');

        $salesOrderItem->delete();

        return response()->noContent();
    }

    private function lineTotal(float $quantity, float $unitPrice): float
    {
        return round(max(0, $quantity) * max(0, $unitPrice), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'sales_order_id' => [$presence, 'integer', 'exists:sales_orders,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'quantity' => [$presence, 'numeric', 'min:0.01', 'max:100000'],
            'unit_price' => [$presence, 'numeric', 'min:0', 'max:999999999.99'],
        ];
    }
}
