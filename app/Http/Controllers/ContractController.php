<?php

namespace App\Http\Controllers;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Opportunity::class);

        $status = (string) $request->query('status', '');

        $contracts = Contract::query()
            ->with(['account:id,name', 'opportunity:id,name'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Contract $contract): array => [
                'id' => $contract->getKey(),
                'contract_number' => $contract->contract_number,
                'status' => $contract->status->value,
                'status_tone' => $contract->status->tone(),
                'account' => $contract->account?->only(['id', 'name']),
                'opportunity' => $contract->opportunity?->only(['id', 'name']),
                'start_date' => $contract->start_date?->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
                'sales_orders_count' => $contract->salesOrders()->count(),
            ]);

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
            'filters' => ['status' => $status],
            'statuses' => ContractStatus::options(),
        ]);
    }
}
