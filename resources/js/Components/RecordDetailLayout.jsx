import { Link } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import { cn } from '../lib/format';
import Tabs from './UI/Tabs';

function StageTracker({ steps = [], current }) {
    if (steps.length === 0) return null;

    return (
        <ol className="flex flex-wrap items-center gap-x-2 gap-y-1.5 border-b border-slate-200 bg-white px-4 py-3 sm:px-6">
            {steps.map((step, index) => {
                const isCurrent = step.value === current;
                const isPast = steps.findIndex((candidate) => candidate.value === current) > index;

                return (
                    <li key={step.value} className="flex items-center gap-2">
                        <span
                            className={cn(
                                'flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold ring-1 ring-inset',
                                isCurrent && 'bg-brand-600 text-white ring-brand-600',
                                isPast && 'bg-brand-50 text-brand-700 ring-brand-200',
                                !isCurrent && !isPast && 'bg-slate-50 text-slate-400 ring-slate-200',
                            )}
                        >
                            {isPast ? <Check className="h-3 w-3" /> : index + 1}
                        </span>
                        <span
                            className={cn(
                                'text-xs font-medium',
                                isCurrent ? 'text-brand-700' : isPast ? 'text-slate-700' : 'text-slate-400',
                            )}
                        >
                            {step.label}
                        </span>
                        {index < steps.length - 1 && <span className="hidden h-px w-5 bg-slate-200 sm:block" />}
                    </li>
                );
            })}
        </ol>
    );
}

export default function RecordDetailLayout({
    title,
    subtitle,
    badges,
    actions,
    highlights = [],
    stage,
    tabs,
    activeTab,
    onTabChange,
    children,
    activityPanel,
    backHref,
    backLabel = 'Back',
}) {
    return (
        <div className="space-y-4">
            {backHref && (
                <Link
                    href={backHref}
                    className="inline-flex items-center gap-1 text-xs font-medium text-slate-500 transition hover:text-slate-800"
                >
                    <ArrowLeft className="h-3.5 w-3.5" />
                    {backLabel}
                </Link>
            )}

            <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div className="bg-gradient-to-r from-slate-50 via-white to-white px-4 py-4 sm:px-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                {badges}
                                <span className="text-xs text-slate-300">·</span>
                                <span className="text-xs font-medium text-slate-500">{subtitle}</span>
                            </div>
                            <h1 className="mt-1 truncate text-xl font-semibold tracking-tight text-slate-900">
                                {title}
                            </h1>
                        </div>

                        {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                    </div>

                    {highlights.length > 0 && (
                        <dl className="mt-4 grid grid-cols-2 gap-4 border-t border-slate-200 pt-4 sm:grid-cols-4">
                            {highlights.map((highlight) => (
                                <div key={highlight.label} className="min-w-0">
                                    <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                                        {highlight.label}
                                    </dt>
                                    <dd className={cn('mt-1 truncate text-sm font-semibold text-slate-900', highlight.className)}>
                                        {highlight.value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </div>

                {stage && <StageTracker steps={stage.steps} current={stage.current} />}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="min-w-0 lg:col-span-2">
                    <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        {tabs && onTabChange && <Tabs tabs={tabs} active={activeTab} onChange={onTabChange} />}
                        <div className="p-4 sm:p-5">{children}</div>
                    </div>
                </div>

                <aside className="min-w-0 space-y-4">{activityPanel}</aside>
            </div>
        </div>
    );
}
