<?php

namespace App\Http\Controllers\Api;

use App\Enums\SalesOrderStatus;
use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SalesOrderApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'orders.view');

        $orders = SalesOrder::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('account_id'), fn ($query) => $query->where('account_id', $request->integer('account_id')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->with(['account:id,name', 'opportunity:id,name', 'contract:id,contract_number', 'user:id,name'])
            ->orderByDesc('order_date')
            ->paginate($this->perPage($request));

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'orders.view');

        $order = SalesOrder::create([
            ...$request->validate($this->rules()),
            'order_number' => $request->string('order_number')->value()
                ?: 'SO-'.now()->format('Y').'-'.str_pad((string) (SalesOrder::query()->max('id') + 1), 5, '0', STR_PAD_LEFT),
        ]);

        return response()->json($order->load('account:id,name'), 201);
    }

    public function show(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $this->guardPermission($request, 'orders.view');

        return response()->json($salesOrder->load(['account', 'opportunity', 'contract', 'user:id,name', 'items.product']));
    }

    public function update(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $this->guardPermission($request, 'orders.view');

        $salesOrder->update($request->validate($this->rules($salesOrder)));

        return response()->json($salesOrder->refresh());
    }

    public function updateStatus(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $this->guardPermission($request, 'orders.view');

        $data = $request->validate([
            'status' => ['required', Rule::enum(SalesOrderStatus::class)],
        ]);

        $salesOrder->update(['status' => $data['status']]);

        return response()->json($salesOrder->refresh()->load('account:id,name'));
    }

    public function destroy(Request $request, SalesOrder $salesOrder): Response
    {
        $this->guardPermission($request, 'orders.view');

        $salesOrder->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?SalesOrder $order = null): array
    {
        $presence = $order === null ? 'required' : 'sometimes';

        return [
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'account_id' => [$presence, 'integer', 'exists:accounts,id'],
            'opportunity_id' => [$presence, 'integer', 'exists:opportunities,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'order_number' => ['sometimes', 'string', 'max:100', Rule::unique('sales_orders', 'order_number')->ignore($order)],
            'order_date' => [$presence, 'date'],
            'subtotal' => ['sometimes', 'numeric', 'min:0'],
            'total_amount' => [$presence, 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::enum(SalesOrderStatus::class)],
        ];
    }
}
