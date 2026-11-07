import { Link, usePage } from '@inertiajs/react';
import { CalendarClock, DollarSign, Percent, Target, TrendingUp, Trophy } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import Badge from '../../Components/UI/Badge';
import Card, { CardBody, CardHeader } from '../../Components/UI/Card';
import DeferredSection from '../../Components/UI/DeferredSection';
import StatCard from '../../Components/UI/StatCard';
import TargetVsAchievement from '../../Components/TargetVsAchievement';
import { cn, formatMoney, formatNumber } from '../../lib/format';

export default function Index({ kpis, pipeline, leadFunnel, targeting, yearOptions = [], recentActivities, topOpportunities }) {
    const { settings } = usePage().props;
    const currency = settings?.currency ?? 'USD';
    const maxPipeline = Math.max(1, ...pipeline.map((stage) => stage.total));
    const totalLeads = leadFunnel.reduce((sum, status) => sum + status.count, 0);

    return (
        <AppLayout title="Dashboard">
            <div className="space-y-5">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-slate-900">Revenue overview</h1>
                        <p className="text-sm text-slate-500">Pipeline health for the current quarter.</p>
                    </div>
                    <Link
                        href="/opportunities"
                        className="text-xs font-medium text-brand-600 hover:text-brand-700"
                    >
                        Open pipeline board →
                    </Link>
                </header>

                <TargetVsAchievement targeting={targeting} yearOptions={yearOptions} />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Open pipeline"
                        value={formatMoney(kpis.openPipeline, currency)}
                        hint={`${formatNumber(kpis.openDeals)} active deals`}
                        icon={DollarSign}
                        tone="brand"
                    />
                    <StatCard
                        label="Weighted pipeline"
                        value={formatMoney(kpis.weightedPipeline, currency)}
                        hint="Probability adjusted"
                        icon={TrendingUp}
                        tone="violet"
                    />
                    <StatCard
                        label="Won this quarter"
                        value={formatMoney(kpis.wonValue, currency)}
                        hint={`${kpis.winRate}% win rate`}
                        icon={Trophy}
                        tone="emerald"
                    />
                    <StatCard
                        label="New leads"
                        value={formatNumber(kpis.newLeads)}
                        hint={`${kpis.overdueActivities} overdue activities`}
                        icon={Target}
                        tone={kpis.overdueActivities > 0 ? 'rose' : 'slate'}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader title="Pipeline by stage" description="Value and deal count across the sales process." />
                        <CardBody className="space-y-3.5">
                            {pipeline.map((stage) => (
                                <div key={stage.stage}>
                                    <div className="flex items-center justify-between gap-3 text-xs">
                                        <span className="font-medium text-slate-700">{stage.label}</span>
                                        <span className="text-slate-500">
                                            {formatMoney(stage.total, currency)} · {formatNumber(stage.count)} deals
                                        </span>
                                    </div>
                                    <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            className={cn(
                                                'h-full rounded-full',
                                                stage.tone === 'emerald'
                                                    ? 'bg-emerald-500'
                                                    : stage.tone === 'rose'
                                                      ? 'bg-rose-400'
                                                      : 'bg-brand-500',
                                            )}
                                            style={{ width: `${Math.max(2, (stage.total / maxPipeline) * 100)}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title="Lead funnel" description={`${formatNumber(totalLeads)} leads in total`} />
                        <CardBody className="space-y-2.5">
                            {leadFunnel.map((status) => (
                                <div key={status.status} className="flex items-center justify-between gap-2">
                                    <Badge tone={status.tone}>{status.label}</Badge>
                                    <span className="text-sm font-semibold text-slate-900">{formatNumber(status.count)}</span>
                                </div>
                            ))}
                        </CardBody>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader
                            title="Top open deals"
                            description="Largest opportunities still in play."
                            action={<Link href="/opportunities" className="text-xs font-medium text-brand-600">View board</Link>}
                        />
                        <DeferredSection data="topOpportunities" rows={4}>
                            {() => (
                                <ul className="divide-y divide-slate-100">
                                    {(topOpportunities ?? []).length === 0 && (
                                        <li className="px-4 py-8 text-center text-sm text-slate-500">No open deals.</li>
                                    )}
                                    {(topOpportunities ?? []).map((opportunity) => (
                                        <li key={opportunity.id}>
                                            <Link
                                                href={`/opportunities/${opportunity.id}`}
                                                className="flex items-center justify-between gap-3 px-4 py-3 transition hover:bg-slate-50"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-slate-900">{opportunity.name}</p>
                                                    <p className="truncate text-xs text-slate-500">
                                                        {opportunity.account} · {opportunity.probability_pct}% · closes{' '}
                                                        {opportunity.close_date ?? '—'}
                                                    </p>
                                                </div>
                                                <div className="shrink-0 text-right">
                                                    <p className="text-sm font-semibold text-slate-900">
                                                        {formatMoney(opportunity.amount, currency)}
                                                    </p>
                                                    <Badge tone={opportunity.stage_tone} dot={false}>
                                                        {opportunity.stage}
                                                    </Badge>
                                                </div>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </DeferredSection>
                    </Card>

                    <Card>
                        <CardHeader title="Recent activity" description="Latest calls, emails and tasks." />
                        <DeferredSection data="recentActivities" rows={4}>
                            {() => (
                                <ul className="divide-y divide-slate-100">
                                    {(recentActivities ?? []).length === 0 && (
                                        <li className="px-4 py-8 text-center text-sm text-slate-500">No activity yet.</li>
                                    )}
                                    {(recentActivities ?? []).map((activity) => (
                                        <li key={activity.id} className="flex items-start gap-3 px-4 py-3">
                                            <span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                                <CalendarClock className="h-3.5 w-3.5" />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm text-slate-900">{activity.subject}</p>
                                                <p className="text-[11px] text-slate-500">
                                                    {activity.type} · {activity.user ?? 'System'} · {activity.created_at}
                                                </p>
                                            </div>
                                            <Badge tone={activity.type_tone} dot={false}>
                                                {activity.type}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </DeferredSection>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
