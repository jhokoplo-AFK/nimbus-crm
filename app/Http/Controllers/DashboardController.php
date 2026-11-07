<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Services\TargetAchievementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TargetAchievementService $targeting): Response
    {
        $this->authorize('viewAny', Opportunity::class);

        $year = (int) $request->integer('year');

        if ($year < 2000 || $year > 2100) {
            $year = now()->year;
        }

        $quarterStart = now()->startOfQuarter()->toDateString();
        $quarterEnd = now()->endOfQuarter()->toDateString();

        $openPipeline = (float) Opportunity::query()->open()->sum('amount');

        // value() would replace the select list, so read the aggregate row directly.
        $weightedRow = Opportunity::query()
            ->open()
            ->selectRaw('COALESCE(SUM(amount * probability_pct / 100), 0) as weighted')
            ->first();

        $weightedPipeline = (float) ($weightedRow->weighted ?? 0);

        $wonQuery = Opportunity::query()
            ->where('stage', OpportunityStage::ClosedWon->value)
            ->whereBetween('close_date', [$quarterStart, $quarterEnd]);

        $lostQuery = Opportunity::query()
            ->where('stage', OpportunityStage::ClosedLost->value)
            ->whereBetween('close_date', [$quarterStart, $quarterEnd]);

        $wonCount = (clone $wonQuery)->count();
        $lostCount = (clone $lostQuery)->count();
        $closedCount = $wonCount + $lostCount;

        $leadCounts = Lead::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stageBreakdown = collect(OpportunityStage::board())
            ->map(function (OpportunityStage $stage): array {
                $query = Opportunity::query()->where('stage', $stage->value);

                return [
                    'stage' => $stage->value,
                    'label' => $stage->label(),
                    'tone' => $stage->tone(),
                    'count' => (clone $query)->count(),
                    'total' => (float) (clone $query)->sum('amount'),
                ];
            })
            ->values()
            ->all();

        $leadBreakdown = collect(LeadStatus::cases())
            ->map(fn (LeadStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'tone' => $status->tone(),
                'count' => (int) ($leadCounts[$status->value] ?? 0),
            ])
            ->values()
            ->all();

        return Inertia::render('Dashboard/Index', [
            'kpis' => [
                'openPipeline' => $openPipeline,
                'weightedPipeline' => $weightedPipeline,
                'wonValue' => (float) (clone $wonQuery)->sum('amount'),
                'winRate' => $closedCount > 0 ? round(($wonCount / $closedCount) * 100, 1) : 0.0,
                'openDeals' => Opportunity::query()->open()->count(),
                'newLeads' => (int) ($leadCounts[LeadStatus::New->value] ?? 0),
                'overdueActivities' => Activity::query()->overdue()->count(),
            ],
            'pipeline' => $stageBreakdown,
            'leadFunnel' => $leadBreakdown,
            'targeting' => $targeting->forYear($year),
            'yearOptions' => $targeting->years(),

            // Inertia v2 deferred props: the shell renders first, these panels
            // stream in on their own request.
            'recentActivities' => Inertia::defer(fn (): array => Activity::query()
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(8)
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
                ->all()),

            'topOpportunities' => Inertia::defer(fn (): array => Opportunity::query()
                ->open()
                ->with('account:id,name')
                ->orderByDesc('amount')
                ->limit(5)
                ->get()
                ->map(fn (Opportunity $opportunity): array => [
                    'id' => $opportunity->getKey(),
                    'name' => $opportunity->name,
                    'account' => $opportunity->account?->name,
                    'stage' => $opportunity->stage->value,
                    'stage_tone' => $opportunity->stage->tone(),
                    'amount' => (float) $opportunity->amount,
                    'probability_pct' => $opportunity->probability_pct,
                    'close_date' => $opportunity->close_date?->toDateString(),
                ])
                ->all()),
        ]);
    }
}
