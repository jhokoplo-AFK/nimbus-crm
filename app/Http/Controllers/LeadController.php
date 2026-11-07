<?php

namespace App\Http\Controllers;

use App\Actions\ConvertLeadAction;
use App\Http\Requests\ConvertLeadRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Activity;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Lead::class);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $leads = Lead::query()
            ->search($search)
            ->withStatus($status !== '' ? $status : null)
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Lead $lead): array => [
                'id' => $lead->getKey(),
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'company' => $lead->company,
                'title' => $lead->title,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'lead_source' => $lead->lead_source,
                'status' => $lead->status->value,
                'status_label' => $lead->status->label(),
                'status_tone' => $lead->status->tone(),
                'created_at' => $lead->created_at?->toDateString(),
                'is_locked' => $lead->isLocked(),
                'can_convert' => $request->user()->can('convert', $lead),
            ]);

        return Inertia::render('Leads/Index', [
            'leads' => $leads,
            'filters' => ['search' => $search, 'status' => $status],
            'statusCounts' => Lead::query()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'sources' => Lead::query()
                ->whereNotNull('lead_source')
                ->distinct()
                ->orderBy('lead_source')
                ->pluck('lead_source'),
        ]);
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $lead = Lead::create($request->validated());

        return redirect()
            ->route('leads.show', $lead)
            ->with('success', "Lead {$lead->fullName()} created.");
    }

    public function show(Request $request, Lead $lead): Response
    {
        $this->authorize('view', $lead);

        return Inertia::render('Leads/Show', [
            'lead' => [
                'id' => $lead->getKey(),
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'company' => $lead->company,
                'title' => $lead->title,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'lead_source' => $lead->lead_source,
                'status' => $lead->status->value,
                'status_label' => $lead->status->label(),
                'status_tone' => $lead->status->tone(),
                'notes' => $lead->notes,
                'is_locked' => $lead->isLocked(),
                'converted_at' => $lead->converted_at?->toDayDateTimeString(),
                'created_at' => $lead->created_at?->toFormattedDateString(),
                'converted' => $lead->converted_account_id === null ? null : [
                    'account' => $lead->convertedAccount?->only(['id', 'name']),
                    'contact' => $lead->convertedContact?->only(['id', 'first_name', 'last_name', 'email']),
                    'opportunity' => $lead->convertedOpportunity?->only(['id', 'name', 'amount', 'stage']),
                ],
            ],
            'activities' => $lead->activities()
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(25)
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
            'can' => [
                'update' => $request->user()->can('update', $lead),
                'convert' => $request->user()->can('convert', $lead),
                'delete' => $request->user()->can('delete', $lead),
            ],
        ]);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $lead->update($request->validated());

        return back()->with('success', 'Lead updated.');
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return redirect()
            ->route('leads.index')
            ->with('success', 'Lead deleted.');
    }

    public function convert(ConvertLeadRequest $request, Lead $lead, ConvertLeadAction $action): RedirectResponse
    {
        $result = $action->execute($lead, $request->validated(), $request->user());

        return redirect()
            ->route('opportunities.show', $result->opportunity)
            ->with('success', "Lead converted. {$result->account->name} is now in your pipeline.");
    }
}
