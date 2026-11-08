<?php

namespace App\Http\Controllers;

use App\Actions\ApproveQuotationAction;
use App\Actions\RejectQuotationAction;
use App\Enums\QuotationStatus;
use App\Http\Requests\RejectQuotationRequest;
use App\Http\Requests\StoreQuotationRequest;
use App\Http\Requests\UpdateQuotationRequest;
use App\Models\Opportunity;
use App\Models\Pricebook;
use App\Models\Quotation;
use App\Services\PriceApprovalService;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Quotation::class);

        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $quotations = Quotation::query()
            ->with(['opportunity:id,name,account_id', 'opportunity.account:id,name', 'pricebook:id,name'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('quote_number', 'like', "%{$search}%")
                ->orWhereHas('opportunity', fn ($opportunity) => $opportunity->where('name', 'like', "%{$search}%"))))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Quotation $quotation): array => [
                'id' => $quotation->getKey(),
                'quote_number' => $quotation->quote_number,
                'revision_number' => $quotation->revision_number,
                'is_primary' => $quotation->is_primary,
                'status' => $quotation->status->value,
                'status_tone' => $quotation->status->tone(),
                'opportunity_id' => $quotation->opportunity_id,
                'opportunity' => $quotation->opportunity?->name,
                'account' => $quotation->opportunity?->account?->name,
                'pricebook' => $quotation->pricebook?->name,
                'grand_total' => (float) $quotation->grand_total,
                'expiration_date' => $quotation->expiration_date?->toDateString(),
                'created_at' => $quotation->created_at?->toDateString(),
            ]);

        return Inertia::render('Quotations/Index', [
            'quotations' => $quotations,
            'filters' => ['status' => $status, 'search' => $search],
            'statuses' => QuotationStatus::options(),
        ]);
    }

    public function create(Request $request, PriceApprovalService $approvals): Response
    {
        $this->authorize('create', Quotation::class);

        $pricebooks = Pricebook::query()
            ->active()
            ->with(['entries' => fn ($query) => $query
                ->whereHas('product', fn ($product) => $product->where('is_active', true))
                ->with('product:id,sku,name')])
            ->orderByDesc('is_standard')
            ->orderBy('name')
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
            ->all();

        $opportunities = Opportunity::query()
            ->with('account:id,name')
            ->orderByDesc('amount')
            ->get(['id', 'account_id', 'name', 'amount', 'stage'])
            ->map(fn (Opportunity $opportunity): array => [
                'id' => $opportunity->getKey(),
                'name' => $opportunity->name,
                'account' => $opportunity->account?->name,
                'amount' => (float) $opportunity->amount,
                'stage' => $opportunity->stage->value,
            ])
            ->all();

        return Inertia::render('Quotations/Create', [
            'pricebooks' => $pricebooks,
            'opportunities' => $opportunities,
            'preselectedOpportunityId' => $request->integer('opportunity') ?: null,
            'defaults' => [
                'taxRate' => (float) config('crm.tax_rate', 11),
                'expirationDate' => now()->addDays(30)->toDateString(),
                'paymentTermDays' => 30,
            ],
            'approvalPolicy' => $approvals->policyFor($request->user()),
        ]);
    }

    public function store(StoreQuotationRequest $request, QuotationService $service): RedirectResponse
    {
        $data = $request->validated();

        $quotation = $service->create(
            Opportunity::query()->findOrFail($data['opportunity_id']),
            Pricebook::query()->findOrFail($data['pricebook_id']),
            $data,
            $request->user(),
        );

        $message = $quotation->isAwaitingApproval()
            ? "Quotation {$quotation->quote_number} submitted for manager approval."
            : "Quotation {$quotation->quote_number} created.";

        return redirect()->route('quotations.show', $quotation)->with('success', $message);
    }

    public function show(Request $request, Quotation $quotation, PriceApprovalService $approvals): Response
    {
        $this->authorize('view', $quotation);

        $quotation->load([
            'lineItems.product:id,sku,name',
            'opportunity.account:id,name',
            'pricebook:id,name',
            'approvedBy:id,name',
            'creditApprovedBy:id,name',
        ]);

        $account = $quotation->opportunity?->account;

        return Inertia::render('Quotations/Show', [
            'quotation' => [
                'id' => $quotation->getKey(),
                'quote_number' => $quotation->quote_number,
                'revision_number' => $quotation->revision_number,
                'is_primary' => $quotation->is_primary,
                'status' => $quotation->status->value,
                'status_tone' => $quotation->status->tone(),
                'required_approval_level' => $quotation->required_approval_level,
                'required_approver' => $approvals->requiredRoleLabel($quotation->required_approval_level),
                'subtotal' => (float) $quotation->subtotal,
                'line_discount_amount' => (float) $quotation->line_discount_amount,
                'discount_type' => $quotation->discount_type->value,
                'discount_value' => (float) $quotation->discount_value,
                'discount_amount' => (float) $quotation->discount_amount,
                'tax_rate' => (float) $quotation->tax_rate,
                'tax_amount' => (float) $quotation->tax_amount,
                'grand_total' => (float) $quotation->grand_total,
                'effective_discount_pct' => $quotation->effectiveDiscountPct(),
                'expiration_date' => $quotation->expiration_date?->toDateString(),
                'payment_term_days' => $quotation->payment_term_days,
                'credit_status' => $quotation->credit_status->value,
                'credit_status_tone' => $quotation->credit_status->tone(),
                'credit_required_level' => $quotation->credit_required_level,
                'credit_required_approver' => $approvals->requiredRoleLabel($quotation->credit_required_level),
                'credit_approved_by' => $quotation->creditApprovedBy?->name,
                'credit_approved_at' => $quotation->credit_approved_at?->toDayDateTimeString(),
                'credit' => [
                    'limit' => $account?->credit_limit === null ? null : (float) $account->credit_limit,
                    'balance_due' => (float) ($account?->balance_due ?? 0),
                    'overdue_balance' => (float) ($account?->overdue_balance ?? 0),
                    'exposure' => (float) ($account?->balance_due ?? 0) + (float) $quotation->grand_total,
                    'available_credit' => $account?->availableCredit(),
                ],
                'approved_by' => $quotation->approvedBy?->name,
                'approved_at' => $quotation->approved_at?->toDayDateTimeString(),
                'rejection_reason' => $quotation->rejection_reason,
                'created_at' => $quotation->created_at?->toDayDateTimeString(),
                'opportunity' => $quotation->opportunity === null ? null : [
                    'id' => $quotation->opportunity->getKey(),
                    'name' => $quotation->opportunity->name,
                    'account' => $quotation->opportunity->account?->name,
                ],
                'pricebook' => $quotation->pricebook?->name,
                'lines' => $quotation->lineItems->map(fn ($line): array => [
                    'id' => $line->getKey(),
                    'product' => $line->product?->name,
                    'sku' => $line->product?->sku,
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'discount_pct' => (float) $line->discount_pct,
                    'discount_amount' => round((float) $line->unit_price * (float) $line->quantity * ((float) $line->discount_pct / 100), 2),
                    'total_price' => (float) $line->total_price,
                ])->all(),
            ],
            'can' => [
                'update' => $request->user()->can('update', $quotation),
                'revise' => $request->user()->can('revise', $quotation),
                'approve' => $request->user()->can('approve', $quotation),
                'reject' => $request->user()->can('reject', $quotation),
                'send' => $request->user()->can('send', $quotation),
            ],
        ]);
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation, QuotationService $service): RedirectResponse
    {
        $service->update($quotation, $request->validated(), $request->user());

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', "Quotation {$quotation->quote_number} repriced.");
    }

    public function revise(Request $request, Quotation $quotation, QuotationService $service): RedirectResponse
    {
        $this->authorize('revise', $quotation);

        $revision = $service->revise($quotation, $request->user());

        return redirect()
            ->route('quotations.show', $revision)
            ->with('success', "Revision R{$revision->revision_number} created from {$quotation->quote_number}.");
    }

    public function approve(Request $request, Quotation $quotation, ApproveQuotationAction $action): RedirectResponse
    {
        $this->authorize('approve', $quotation);

        $action->execute($quotation, $request->user());

        return back()->with('success', "Quotation {$quotation->quote_number} approved.");
    }

    public function reject(RejectQuotationRequest $request, Quotation $quotation, RejectQuotationAction $action): RedirectResponse
    {
        $action->execute($quotation, $request->user(), $request->validated('reason'));

        return back()->with('success', "Quotation {$quotation->quote_number} rejected.");
    }

    public function send(Request $request, Quotation $quotation, QuotationService $service): RedirectResponse
    {
        $this->authorize('send', $quotation);

        $service->markSent($quotation, $request->user());

        return back()->with('success', "Quotation {$quotation->quote_number} marked as sent.");
    }
}
