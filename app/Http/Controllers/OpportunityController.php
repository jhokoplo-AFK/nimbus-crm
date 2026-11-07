<?php

namespace App\Http\Controllers;

use App\Actions\ChangeOpportunityStageAction;
use App\Enums\OpportunityStage;
use App\Http\Requests\StoreOpportunityRequest;
use App\Http\Requests\UpdateOpportunityStageRequest;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Pricebook;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Opportunity::class);

        $opportunities = Opportunity::query()
            ->search(trim((string) $request->query('search', '')))
            ->with(['account:id,name', 'primaryContact:id,first_name,last_name'])
            ->orderByDesc('amount')
            ->get()
            ->map(fn (Opportunity $opportunity): array => [
                'id' => $opportunity->getKey(),
                'name' => $opportunity->name,
                'account_id' => $opportunity->account_id,
                'account' => $opportunity->account?->name,
                'contact' => $opportunity->primaryContact?->full_name,
                'stage' => $opportunity->stage->value,
                'amount' => (float) $opportunity->amount,
                'probability_pct' => $opportunity->probability_pct,
                'weighted_amount' => $opportunity->weightedAmount(),
                'close_date' => $opportunity->close_date?->toDateString(),
            ])
            ->all();

        // Totals are recomputed server-side for the initial paint; the board
        // recalculates them locally while a drag is in flight.
        $columns = collect(OpportunityStage::board())
            ->map(fn (OpportunityStage $stage): array => [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'tone' => $stage->tone(),
                'probability' => $stage->defaultProbability(),
                'count' => collect($opportunities)->where('stage', $stage->value)->count(),
                'total' => (float) collect($opportunities)->where('stage', $stage->value)->sum('amount'),
            ])
            ->values()
            ->all();

        return Inertia::render('Opportunities/Kanban', [
            'opportunities' => $opportunities,
            'columns' => $columns,
            'filters' => ['search' => (string) $request->query('search', '')],
            'accounts' => Account::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreOpportunityRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $stage = OpportunityStage::from($data['stage']);

        $opportunity = Opportunity::create([
            ...$data,
            'probability_pct' => $data['probability_pct'] ?? $stage->defaultProbability(),
        ]);

        return redirect()
            ->route('opportunities.show', $opportunity)
            ->with('success', "Opportunity {$opportunity->name} created.");
    }

    public function show(Request $request, Opportunity $opportunity): Response
    {
        $this->authorize('view', $opportunity);

        $opportunity->load([
            'account:id,name,industry',
            'primaryContact:id,first_name,last_name,email,phone',
            'account.primaryContact:id,account_id,first_name,last_name,email',
        ]);

        return Inertia::render('Opportunities/Show', [
            'opportunity' => [
                'id' => $opportunity->getKey(),
                'name' => $opportunity->name,
                'stage' => $opportunity->stage->value,
                'stage_label' => $opportunity->stage->label(),
                'stage_tone' => $opportunity->stage->tone(),
                'amount' => (float) $opportunity->amount,
                'weighted_amount' => $opportunity->weightedAmount(),
                'probability_pct' => $opportunity->probability_pct,
                'close_date' => $opportunity->close_date?->toDateString(),
                'loss_reason' => $opportunity->loss_reason,
                'account' => $opportunity->account?->only(['id', 'name', 'industry']),
                'primary_contact' => $opportunity->primaryContact === null ? null : [
                    'id' => $opportunity->primaryContact->getKey(),
                    'name' => $opportunity->primaryContact->full_name,
                    'email' => $opportunity->primaryContact->email,
                    'phone' => $opportunity->primaryContact->phone,
                ],
                'created_at' => $opportunity->created_at?->toFormattedDateString(),
            ],
            'activities' => $opportunity->activities()
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(30)
                ->get()
                ->map(fn (Activity $activity): array => [
                    'id' => $activity->getKey(),
                    'subject' => $activity->subject,
                    'type' => $activity->type->value,
                    'type_tone' => $activity->type->tone(),
                    'status' => $activity->status->value,
                    'due_date' => $activity->due_date?->toDateString(),
                    'user' => $activity->user?->name,
                    'created_at' => $activity->created_at?->diffForHumans(),
                ])
                ->all(),

            // Related lists stream after the detail shell has painted.
            'quotations' => Inertia::defer(fn (): array => $opportunity->quotations()
                ->with('pricebook:id,name')
                ->orderByDesc('revision_number')
                ->get()
                ->map(fn (Quotation $quotation): array => [
                    'id' => $quotation->getKey(),
                    'quote_number' => $quotation->quote_number,
                    'revision_number' => $quotation->revision_number,
                    'is_primary' => $quotation->is_primary,
                    'status' => $quotation->status->value,
                    'status_tone' => $quotation->status->tone(),
                    'pricebook' => $quotation->pricebook?->name,
                    'grand_total' => (float) $quotation->grand_total,
                    'expiration_date' => $quotation->expiration_date?->toDateString(),
                ])
                ->all()),
            'contracts' => Inertia::defer(fn (): array => $opportunity->contracts()
                ->get()
                ->map(fn (Contract $contract): array => [
                    'id' => $contract->getKey(),
                    'contract_number' => $contract->contract_number,
                    'status' => $contract->status->value,
                    'status_tone' => $contract->status->tone(),
                    'start_date' => $contract->start_date?->toDateString(),
                    'end_date' => $contract->end_date?->toDateString(),
                ])
                ->all()),
            'salesOrders' => Inertia::defer(fn (): array => $opportunity->salesOrders()
                ->get()
                ->map(fn (SalesOrder $order): array => [
                    'id' => $order->getKey(),
                    'order_number' => $order->order_number,
                    'order_date' => $order->order_date?->toDateString(),
                    'total_amount' => (float) $order->total_amount,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'status_tone' => $order->status->tone(),
                ])
                ->all()),

            // Everything the "New quote" flow needs without a second request.
            'pricebooks' => Pricebook::query()
                ->active()
                ->with(['entries' => fn ($query) => $query
                    ->whereHas('product', fn ($product) => $product->where('is_active', true))
                    ->with('product:id,sku,name')])
                ->orderByDesc('is_standard')
                ->get()
                ->map(fn (Pricebook $pricebook): array => [
                    'id' => $pricebook->getKey(),
                    'name' => $pricebook->name,
                    'is_standard' => $pricebook->is_standard,
                    'products' => $pricebook->entries->map(fn ($entry): array => [
                        'product_id' => $entry->product_id,
                        'sku' => $entry->product->sku,
                        'name' => $entry->product->name,
                        'list_price' => (float) $entry->list_price,
                    ])->values()->all(),
                ])
                ->all(),

            'can' => [
                'update' => $request->user()->can('update', $opportunity),
                'updateStage' => $request->user()->can('updateStage', $opportunity),
                'createQuotation' => $request->user()->can('create', Quotation::class),
            ],
        ]);
    }

    /**
     * The Kanban board's single write path.
     */
    public function updateStage(
        UpdateOpportunityStageRequest $request,
        Opportunity $opportunity,
        ChangeOpportunityStageAction $action,
    ): RedirectResponse {
        $validated = $request->validated();
        $stage = OpportunityStage::from($validated['stage']);

        // The action saves through OpportunityObserver, which runs the
        // Closed-Won automation (probability lock + Draft Sales Order).
        $action->execute($opportunity, $stage, $validated['loss_reason'] ?? null);

        return back()->with('success', "{$opportunity->name} moved to {$stage->label()}.");
    }
}
