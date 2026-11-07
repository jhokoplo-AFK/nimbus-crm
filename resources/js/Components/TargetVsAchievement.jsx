import { router } from '@inertiajs/react';
import { useState } from 'react';
import { cn, formatMoney } from '../lib/format';
import Badge from './UI/Badge';
import Card, { CardBody, CardHeader } from './UI/Card';
import { Select } from './UI/Input';

const DIMENSIONS = [
    { key: 'by_sales', label: 'By sales', first: 'Sales rep' },
    { key: 'by_brand', label: 'By brand', first: 'Brand' },
    { key: 'by_customer', label: 'By customer', first: 'Customer' },
    { key: 'by_product', label: 'By product', first: 'Product' },
];

function toneFor(attainment) {
    if (attainment >= 100) return { bar: 'bg-emerald-500', text: 'text-emerald-700', badge: 'emerald' };
    if (attainment >= 75) return { bar: 'bg-amber-500', text: 'text-amber-700', badge: 'amber' };
    return { bar: 'bg-rose-500', text: 'text-rose-700', badge: 'rose' };
}

function SummaryTile({ label, value, hint, tone }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{label}</p>
            <p className={cn('mt-1.5 text-2xl font-semibold tracking-tight', tone ?? 'text-slate-900')}>{value}</p>
            {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
        </div>
    );
}

export default function TargetVsAchievement({ targeting, yearOptions = [] }) {
    const [dimension, setDimension] = useState('by_sales');

    if (!targeting) return null;

    const summary = targeting.summary;
    const summaryTone = toneFor(summary.attainment);
    const rows = targeting[dimension] ?? [];
    const active = DIMENSIONS.find((item) => item.key === dimension) ?? DIMENSIONS[0];

    const changeYear = (year) => {
        router.get('/dashboard', { year }, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <section className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold tracking-tight text-slate-900">Target vs achievement</h2>
                    <p className="text-sm text-slate-500">
                        Net booked revenue against annual, quarterly, monthly, rep and dimension targets.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-xs text-slate-500">Period</span>
                    <Select
                        value={targeting.year}
                        onChange={(event) => changeYear(event.target.value)}
                        className="w-28"
                        aria-label="Reporting year"
                    >
                        {(yearOptions.length > 0 ? yearOptions : [targeting.year]).map((year) => (
                            <option key={year} value={year}>{year}</option>
                        ))}
                    </Select>
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <SummaryTile
                    label="Annual target"
                    value={formatMoney(summary.target)}
                    hint={`${summary.orders} booked orders`}
                />
                <SummaryTile
                    label="Achieved"
                    value={formatMoney(summary.achieved)}
                    hint={`Average deal ${formatMoney(summary.average_deal)}`}
                />
                <SummaryTile
                    label="Attainment"
                    value={`${summary.attainment}%`}
                    hint={summary.attainment >= 100 ? 'Target met' : 'Below target'}
                    tone={summaryTone.text}
                />
                <SummaryTile
                    label="Gap to target"
                    value={formatMoney(summary.gap)}
                    hint={summary.gap >= 0 ? 'Ahead of plan' : 'Remaining to close'}
                    tone={summary.gap >= 0 ? 'text-emerald-700' : 'text-rose-700'}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader
                        title="Monthly target vs achievement"
                        description={`Calendar year ${targeting.year} · bars are capped at 100% attainment`}
                    />
                    <CardBody className="space-y-2.5">
                        {targeting.monthly.map((month) => {
                            const tone = toneFor(month.attainment);

                            return (
                                <div key={month.month} className="grid grid-cols-[2.25rem_1fr_auto] items-center gap-2.5">
                                    <span className="text-[11px] font-medium text-slate-500">{month.label}</span>
                                    <div className="h-3.5 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            className={cn('h-full rounded-full transition-all', tone.bar)}
                                            style={{ width: `${Math.min(100, Math.max(month.attainment > 0 ? 2 : 0, month.attainment))}%` }}
                                        />
                                    </div>
                                    <span className="w-40 text-right text-[11px] tabular-nums text-slate-600">
                                        {formatMoney(month.achieved)}
                                        <span className="text-slate-400"> / {formatMoney(month.target)}</span>
                                    </span>
                                </div>
                            );
                        })}
                    </CardBody>
                </Card>

                <div className="space-y-4">
                    <Card>
                        <CardHeader title="Quarterly" description="Target vs booked revenue" />
                        <CardBody className="space-y-2">
                            {targeting.quarterly.map((quarter) => {
                                const tone = toneFor(quarter.attainment);

                                return (
                                    <div key={quarter.quarter} className="rounded-md border border-slate-200 px-3 py-2">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-semibold text-slate-900">{quarter.label}</span>
                                            <Badge tone={tone.badge} dot={false}>{quarter.attainment}%</Badge>
                                        </div>
                                        <p className="mt-0.5 text-[11px] text-slate-500">
                                            {formatMoney(quarter.achieved)} of {formatMoney(quarter.target)}
                                        </p>
                                    </div>
                                );
                            })}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardBody className="space-y-1.5 text-xs text-slate-600">
                            <p className="font-semibold text-slate-700">How this is calculated</p>
                            <p>Achievement = sales order net subtotal, dated by order date.</p>
                            <p>Missing monthly/quarterly targets fall back to an even split of the annual target.</p>
                        </CardBody>
                    </Card>
                </div>
            </div>

            <Card>
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3">
                    <div className="flex flex-wrap gap-1">
                        {DIMENSIONS.map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                onClick={() => setDimension(item.key)}
                                className={cn(
                                    'rounded-md px-2.5 py-1.5 text-xs font-medium transition',
                                    dimension === item.key
                                        ? 'bg-slate-900 text-white'
                                        : 'text-slate-600 hover:bg-slate-100',
                                )}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                    <span className="text-[11px] text-slate-500">{rows.length} rows</span>
                </div>

                <div className="scrollbar-thin overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">{active.first}</th>
                                <th className="px-4 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-slate-500">Target</th>
                                <th className="px-4 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-slate-500">Achieved</th>
                                <th className="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">Attainment</th>
                                <th className="px-4 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-slate-500">Gap</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-10 text-center text-sm text-slate-500">
                                        No targets or revenue recorded for this view yet.
                                    </td>
                                </tr>
                            )}

                            {rows.map((row) => {
                                const tone = toneFor(row.attainment);

                                return (
                                    <tr key={`${dimension}-${row.label}`} className="hover:bg-slate-50/70">
                                        <td className="px-4 py-2.5 font-medium text-slate-900">{row.label}</td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-slate-600">{formatMoney(row.target)}</td>
                                        <td className="px-4 py-2.5 text-right tabular-nums font-medium text-slate-900">{formatMoney(row.achieved)}</td>
                                        <td className="px-4 py-2.5">
                                            <div className="flex items-center gap-2">
                                                <div className="h-1.5 w-24 overflow-hidden rounded-full bg-slate-100">
                                                    <div
                                                        className={cn('h-full rounded-full', tone.bar)}
                                                        style={{ width: `${Math.min(100, row.attainment)}%` }}
                                                    />
                                                </div>
                                                <span className={cn('text-xs font-semibold', tone.text)}>{row.attainment}%</span>
                                            </div>
                                        </td>
                                        <td className={cn('px-4 py-2.5 text-right tabular-nums', row.gap >= 0 ? 'text-emerald-700' : 'text-rose-700')}>
                                            {formatMoney(row.gap)}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </Card>
        </section>
    );
}
