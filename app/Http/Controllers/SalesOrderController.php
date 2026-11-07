<?php

namespace App\Http\Controllers;

use App\Enums\SalesOrderStatus;
use App\Models\Opportunity;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Opportunity::class);

        $status = (string) $request->query('status', '');

        $orders = SalesOrder::query()
            ->with(['account:id,name', 'opportunity:id,name', 'contract:id,contract_number'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('order_date')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SalesOrder $order): array => [
                'id' => $order->getKey(),
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'status_tone' => $order->status->tone(),
                'account' => $order->account?->only(['id', 'name']),
                'opportunity' => $order->opportunity?->only(['id', 'name']),
                'contract_number' => $order->contract?->contract_number,
                'order_date' => $order->order_date?->toDateString(),
                'total_amount' => (float) $order->total_amount,
            ]);

        return Inertia::render('SalesOrders/Index', [
            'orders' => $orders,
            'filters' => ['status' => $status],
            'statuses' => SalesOrderStatus::options(),
            'pendingErp' => SalesOrder::query()->pendingErpSync()->count(),
        ]);
    }
}
