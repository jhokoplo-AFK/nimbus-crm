<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Opportunity::class);

        $search = trim((string) $request->query('search', ''));
        $industry = (string) $request->query('industry', '');

        $accounts = Account::query()
            ->search($search)
            ->withIndustry($industry !== '' ? $industry : null)
            ->withCount(['contacts', 'opportunities', 'contracts'])
            ->withSum('openOpportunities as open_pipeline_value', 'amount')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Account $account): array => [
                'id' => $account->getKey(),
                'name' => $account->name,
                'industry' => $account->industry,
                'annual_revenue' => (float) $account->annual_revenue,
                'credit_limit' => $account->credit_limit === null ? null : (float) $account->credit_limit,
                'balance_due' => (float) $account->balance_due,
                'overdue_balance' => (float) $account->overdue_balance,
                'available_credit' => $account->availableCredit(),
                'phone' => $account->phone,
                'website' => $account->website,
                'contacts_count' => $account->contacts_count,
                'opportunities_count' => $account->opportunities_count,
                'contracts_count' => $account->contracts_count,
                'open_pipeline_value' => (float) ($account->open_pipeline_value ?? 0),
            ]);

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'filters' => ['search' => $search, 'industry' => $industry],
            'industries' => Account::query()
                ->whereNotNull('industry')
                ->distinct()
                ->orderBy('industry')
                ->pluck('industry'),
        ]);
    }

    public function show(Request $request, Account $account): Response
    {
        Gate::authorize('viewAny', Opportunity::class);

        $account->loadCount(['contacts', 'opportunities', 'contracts']);

        return Inertia::render('Accounts/Show', [
            'account' => [
                'id' => $account->getKey(),
                'name' => $account->name,
                'industry' => $account->industry,
                'annual_revenue' => (float) $account->annual_revenue,
                'credit_limit' => $account->credit_limit === null ? null : (float) $account->credit_limit,
                'balance_due' => (float) $account->balance_due,
                'overdue_balance' => (float) $account->overdue_balance,
                'available_credit' => $account->availableCredit(),
                'phone' => $account->phone,
                'website' => $account->website,
                'billing_address' => $account->billing_address,
                'shipping_address' => $account->shipping_address,
                'contacts_count' => $account->contacts_count,
                'open_pipeline_value' => $account->openPipelineValue(),
                'created_at' => $account->created_at?->toFormattedDateString(),
            ],
            'contacts' => $account->contacts()
                ->orderByDesc('is_primary')
                ->orderBy('last_name')
                ->get()
                ->map(fn (Contact $contact): array => [
                    'id' => $contact->getKey(),
                    'name' => $contact->full_name,
                    'title' => $contact->title,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'is_primary' => $contact->is_primary,
                ])
                ->all(),
            'opportunities' => Inertia::defer(fn (): array => $account->opportunities()
                ->orderByDesc('amount')
                ->get()
                ->map(fn (Opportunity $opportunity): array => [
                    'id' => $opportunity->getKey(),
                    'name' => $opportunity->name,
                    'stage' => $opportunity->stage->value,
                    'stage_tone' => $opportunity->stage->tone(),
                    'amount' => (float) $opportunity->amount,
                    'close_date' => $opportunity->close_date?->toDateString(),
                ])
                ->all()),
            'contracts' => Inertia::defer(fn (): array => $account->contracts()
                ->orderByDesc('start_date')
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
            'salesOrders' => Inertia::defer(fn (): array => $account->salesOrders()
                ->orderByDesc('order_date')
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
            'activities' => $account->activities()
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(20)
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
        ]);
    }
}
