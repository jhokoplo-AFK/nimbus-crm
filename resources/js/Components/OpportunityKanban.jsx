import { router } from '@inertiajs/react';
import { CalendarDays, GripVertical, User } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { cn, formatDate, formatMoney } from '../lib/format';
import Badge from './UI/Badge';
import Button from './UI/Button';
import Modal from './UI/Modal';
import { Textarea } from './UI/Input';

export default function OpportunityKanban({ opportunities = [], columns = [], canManage = true }) {
    const [items, setItems] = useState(opportunities);
    const [draggingId, setDraggingId] = useState(null);
    const [overStage, setOverStage] = useState(null);
    const [pendingId, setPendingId] = useState(null);
    const [lossTarget, setLossTarget] = useState(null);
    const [lossReason, setLossReason] = useState('');

    // Server props remain the source of truth after every partial reload.
    useEffect(() => {
        setItems(opportunities);
    }, [opportunities]);

    const board = useMemo(
        () =>
            columns.map((column) => {
                const columnItems = items.filter((item) => item.stage === column.stage);

                return {
                    ...column,
                    items: columnItems,
                    liveTotal: columnItems.reduce((sum, item) => sum + Number(item.amount || 0), 0),
                    weightedTotal: columnItems.reduce(
                        (sum, item) => sum + Number(item.amount || 0) * ((Number(item.probability_pct) || 0) / 100),
                        0,
                    ),
                };
            }),
        [columns, items],
    );

    const move = (opportunity, stage, reason = null) => {
        if (!canManage || !opportunity || opportunity.stage === stage) return;

        if (stage === 'Closed Lost' && !reason) {
            setLossTarget(opportunity);
            setLossReason('');
            return;
        }

        const previous = items;

        setItems((current) =>
            current.map((item) =>
                item.id === opportunity.id
                    ? { ...item, stage, probability_pct: stage === 'Closed Won' ? 100 : item.probability_pct }
                    : item,
            ),
        );
        setPendingId(opportunity.id);

        router.patch(
            `/opportunities/${opportunity.id}/stage`,
            { stage, loss_reason: reason },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['opportunities', 'columns', 'flash'],
                onError: () => setItems(previous),
                onFinish: () => setPendingId(null),
            },
        );
    };

    const confirmLoss = () => {
        if (!lossTarget || !lossReason.trim()) return;

        const opportunity = lossTarget;
        setLossTarget(null);
        move(opportunity, 'Closed Lost', lossReason.trim());
    };

    return (
        <>
            <div className="scrollbar-thin -mx-1 flex gap-3 overflow-x-auto px-1 pb-3">
                {board.map((column) => (
                    <section
                        key={column.stage}
                        onDragOver={(event) => {
                            if (!canManage) return;
                            event.preventDefault();
                            setOverStage(column.stage);
                        }}
                        onDragLeave={() => setOverStage((current) => (current === column.stage ? null : current))}
                        onDrop={(event) => {
                            event.preventDefault();
                            setOverStage(null);

                            const id = Number(event.dataTransfer.getData('text/plain'));
                            const opportunity = items.find((item) => item.id === id);

                            if (opportunity) move(opportunity, column.stage);
                        }}
                        className={cn(
                            'flex w-[290px] shrink-0 flex-col rounded-lg border bg-slate-50/80 transition',
                            overStage === column.stage
                                ? 'border-brand-400 ring-2 ring-brand-100'
                                : 'border-slate-200',
                            column.stage === 'Closed Won' && 'bg-emerald-50/50',
                            column.stage === 'Closed Lost' && 'bg-rose-50/40',
                        )}
                    >
                        <header className="border-b border-slate-200/80 px-3 py-2.5">
                            <div className="flex items-center justify-between gap-2">
                                <Badge tone={column.tone} dot={false}>
                                    {column.label}
                                </Badge>
                                <span className="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-200">
                                    {column.items.length}
                                </span>
                            </div>
                            <div className="mt-2 flex items-baseline justify-between gap-2">
                                <p className="text-sm font-semibold text-slate-900">
                                    {formatMoney(column.liveTotal)}
                                </p>
                                <p className="text-[10px] uppercase tracking-wide text-slate-500">
                                    {formatMoney(column.weightedTotal)} weighted
                                </p>
                            </div>
                        </header>

                        <div className="flex flex-1 flex-col gap-2 p-2">
                            {column.items.length === 0 && (
                                <div className="rounded-md border border-dashed border-slate-300 px-3 py-6 text-center text-xs text-slate-400">
                                    Drop deals here
                                </div>
                            )}

                            {column.items.map((opportunity) => (
                                <article
                                    key={opportunity.id}
                                    draggable={canManage && pendingId !== opportunity.id}
                                    onDragStart={(event) => {
                                        event.dataTransfer.setData('text/plain', String(opportunity.id));
                                        event.dataTransfer.effectAllowed = 'move';
                                        setDraggingId(opportunity.id);
                                    }}
                                    onDragEnd={() => setDraggingId(null)}
                                    onClick={() => router.visit(`/opportunities/${opportunity.id}`)}
                                    className={cn(
                                        'group cursor-pointer rounded-md border border-slate-200 bg-white p-2.5 shadow-sm transition hover:border-slate-300 hover:shadow',
                                        draggingId === opportunity.id && 'opacity-50',
                                        pendingId === opportunity.id && 'animate-pulse',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="line-clamp-2 text-[13px] font-semibold leading-snug text-slate-900">
                                            {opportunity.name}
                                        </p>
                                        {canManage && (
                                            <GripVertical className="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-300 group-hover:text-slate-400" />
                                        )}
                                    </div>

                                    <p className="mt-1 flex items-center gap-1 truncate text-[11px] text-slate-500">
                                        <User className="h-3 w-3" />
                                        {opportunity.account || 'No account'}
                                    </p>

                                    <div className="mt-2 flex items-baseline justify-between">
                                        <span className="text-sm font-semibold text-slate-900">
                                            {formatMoney(opportunity.amount)}
                                        </span>
                                        <span className="text-[11px] font-medium text-slate-500">
                                            {opportunity.probability_pct}%
                                        </span>
                                    </div>

                                    <div className="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            className="h-full rounded-full bg-brand-500"
                                            style={{ width: `${Math.min(100, Number(opportunity.probability_pct) || 0)}%` }}
                                        />
                                    </div>

                                    <div className="mt-2 flex items-center justify-between gap-2">
                                        <span className="flex items-center gap-1 text-[10px] text-slate-400">
                                            <CalendarDays className="h-3 w-3" />
                                            {formatDate(opportunity.close_date)}
                                        </span>

                                        {canManage && (
                                            <select
                                                value={opportunity.stage}
                                                onClick={(event) => event.stopPropagation()}
                                                onChange={(event) => move(opportunity, event.target.value)}
                                                className="max-w-[110px] rounded border-0 bg-slate-50 px-1.5 py-1 text-[10px] font-medium text-slate-600 focus:ring-2 focus:ring-brand-500"
                                                aria-label={`Move ${opportunity.name}`}
                                            >
                                                {columns.map((option) => (
                                                    <option key={option.stage} value={option.stage}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    </section>
                ))}
            </div>

            <Modal
                open={Boolean(lossTarget)}
                onClose={() => setLossTarget(null)}
                title="Close as lost"
                description={lossTarget ? `Tell the team why ${lossTarget.name} was lost.` : ''}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setLossTarget(null)}>
                            Cancel
                        </Button>
                        <Button
                            variant="danger"
                            onClick={confirmLoss}
                            disabled={!lossReason.trim()}
                        >
                            Mark lost
                        </Button>
                    </>
                }
            >
                <Textarea
                    value={lossReason}
                    onChange={(event) => setLossReason(event.target.value)}
                    placeholder="e.g. Budget deferred, lost to competitor on price…"
                    rows={3}
                />
            </Modal>
        </>
    );
}
