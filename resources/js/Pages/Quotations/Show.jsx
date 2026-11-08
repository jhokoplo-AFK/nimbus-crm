import { Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Check, RefreshCw, Send, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Badge from '../../Components/UI/Badge';
import Button from '../../Components/UI/Button';
import Card, { CardBody, CardHeader } from '../../Components/UI/Card';
import { Field, Textarea } from '../../Components/UI/Input';
import Modal from '../../Components/UI/Modal';
import { formatDate, formatMoney, formatMoneyPrecise } from '../../lib/format';

export default function Show({ quotation, can }) {
    const [rejectOpen, setRejectOpen] = useState(false);
    const rejectForm = useForm({ reason: '' });

    // Inertia v2 dynamic polling: while a quote waits for sign-off, quietly
    // refresh just the quotation prop so another manager's decision appears.
    useEffect(() => {
        if (quotation.status !== 'In Review') return undefined;

        const interval = setInterval(() => {
            router.reload({ only: ['quotation', 'can'], preserveScroll: true, preserveState: true });
        }, 12000);

        return () => clearInterval(interval);
    }, [quotation.status]);

    const submitReject = (event) => {
        event.preventDefault();

        rejectForm.post(`/quotations/${quotation.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => {
                setRejectOpen(false);
                rejectForm.reset();
            },
        });
    };

    const lineItemColumns = ['Product', 'Qty', 'Unit price', 'Disc %', 'Line total'];

    return (
        <AppLayout title={quotation.quote_number}>
            <div className="space-y-4">
                <Link href="/quotations" className="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800">
                    <ArrowLeft className="h-3.5 w-3.5" />
                    All quotations
                </Link>

                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-4 px-4 py-4 sm:px-6">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge tone={quotation.status_tone}>{quotation.status}</Badge>
                                <Badge tone="slate" dot={false}>Revision R{quotation.revision_number}</Badge>
                                {quotation.is_primary && <Badge tone="indigo" dot={false}>Primary quote</Badge>}
                                {quotation.status === 'In Review' && (
                                    <span className="inline-flex items-center gap-1 text-[11px] text-amber-700">
                                        <RefreshCw className="h-3 w-3 animate-spin" />
                                        awaiting {quotation.required_approver ?? 'manager'} approval
                                    </span>
                                )}
                            </div>
                            <h1 className="mt-1.5 text-xl font-semibold tracking-tight text-slate-900">
                                {quotation.quote_number}
                            </h1>
                            <p className="text-sm text-slate-500">
                                {quotation.opportunity ? (
                                    <Link
                                        href={`/opportunities/${quotation.opportunity.id}`}
                                        className="font-medium text-brand-700 hover:underline"
                                    >
                                        {quotation.opportunity.name}
                                    </Link>
                                ) : (
                                    'No opportunity'
                                )}
                                {' · '}
                                {quotation.opportunity?.account ?? '—'}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {can.revise && (
                                <Button
                                    variant="secondary"
                                    onClick={() => router.post(`/quotations/${quotation.id}/revise`, {}, { preserveScroll: true })}
                                >
                                    <RefreshCw className="h-3.5 w-3.5" />
                                    New revision
                                </Button>
                            )}
                            {can.approve && (
                                <>
                                    <Button
                                        variant="danger"
                                        onClick={() => setRejectOpen(true)}
                                    >
                                        <X className="h-3.5 w-3.5" />
                                        Reject
                                    </Button>
                                    <Button
                                        onClick={() => router.post(`/quotations/${quotation.id}/approve`, {}, { preserveScroll: true })}
                                    >
                                        <Check className="h-3.5 w-3.5" />
                                        Approve
                                    </Button>
                                </>
                            )}
                            {can.send && quotation.status === 'Approved' && (
                                <Button
                                    variant="subtle"
                                    onClick={() => router.post(`/quotations/${quotation.id}/send`, {}, { preserveScroll: true })}
                                >
                                    <Send className="h-3.5 w-3.5" />
                                    Mark sent
                                </Button>
                            )}
                        </div>
                    </div>

                    <dl className="grid grid-cols-2 gap-4 border-t border-slate-200 bg-slate-50/60 px-4 py-3 sm:grid-cols-4 sm:px-6">
                        <div>
                            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Opportunity</dt>
                            <dd className="mt-0.5 text-sm font-medium text-slate-900">
                                {quotation.opportunity ? (
                                    <Link
                                        href={`/opportunities/${quotation.opportunity.id}`}
                                        className="text-brand-700 hover:underline"
                                    >
                                        {quotation.opportunity.name}
                                    </Link>
                                ) : (
                                    '—'
                                )}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Pricebook</dt>
                            <dd className="mt-0.5 text-sm font-medium text-slate-900">{quotation.pricebook ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Expires</dt>
                            <dd className="mt-0.5 text-sm font-medium text-slate-900">{formatDate(quotation.expiration_date)}</dd>
                        </div>
                        <div>
                            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Approved by</dt>
                            <dd className="mt-0.5 text-sm font-medium text-slate-900">{quotation.approved_by ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Created</dt>
                            <dd className="mt-0.5 text-sm font-medium text-slate-900">{quotation.created_at}</dd>
                        </div>
                    </dl>
                </div>

                {quotation.rejection_reason && (
                    <div className="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        <div>
                            <p className="font-semibold">Rejected</p>
                            <p>{quotation.rejection_reason}</p>
                        </div>
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader title="Line items" description={`${quotation.lines.length} priced line${quotation.lines.length === 1 ? '' : 's'}`} />
                        <div className="scrollbar-thin overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        {lineItemColumns.map((header, index) => (
                                            <th
                                                key={header}
                                                className={`whitespace-nowrap px-4 py-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500 ${
                                                    index === 0 ? 'text-left' : 'text-right'
                                                }`}
                                            >
                                                {header}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {quotation.lines.map((line) => (
                                        <tr key={line.id}>
                                            <td className="px-4 py-2.5">
                                                <p className="font-medium text-slate-900">{line.product}</p>
                                                <p className="text-[11px] text-slate-500">{line.sku}</p>
                                            </td>
                                            <td className="px-4 py-2.5 text-right text-slate-700">{line.quantity}</td>
                                            <td className="px-4 py-2.5 text-right text-slate-700">{formatMoneyPrecise(line.unit_price)}</td>
                                            <td className="px-4 py-2.5 text-right text-slate-700">{line.discount_pct}%</td>
                                            <td className="px-4 py-2.5 text-right font-medium text-slate-900">{formatMoneyPrecise(line.total_price)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    <Card>
                        <CardHeader title="Totals" />
                        <CardBody className="space-y-2 text-sm">
                            <div className="flex justify-between text-slate-600">
                                <span>Subtotal</span>
                                <span className="font-medium text-slate-900">{formatMoneyPrecise(quotation.subtotal)}</span>
                            </div>
                            <div className="flex justify-between text-slate-600">
                                <span>
                                    Discount
                                    {quotation.discount_type === 'percent' ? ` (${quotation.discount_value}%)` : ''}
                                </span>
                                <span className="font-medium text-slate-900">−{formatMoneyPrecise(quotation.discount_amount)}</span>
                            </div>
                            {quotation.effective_discount_pct > 0 && (
                                <p className="text-[11px] text-slate-400">
                                    Effective discount {quotation.effective_discount_pct}% of subtotal
                                </p>
                            )}
                            <div className="flex justify-between text-slate-600">
                                <span>Tax ({quotation.tax_rate}%)</span>
                                <span className="font-medium text-slate-900">{formatMoneyPrecise(quotation.tax_amount)}</span>
                            </div>
                            <div className="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold text-slate-900">
                                <span>Grand total</span>
                                <span>{formatMoneyPrecise(quotation.grand_total)}</span>
                            </div>

                            {quotation.approved_at && (
                                <p className="rounded-md bg-emerald-50 px-3 py-2 text-[11px] text-emerald-700">
                                    Approved by {quotation.approved_by} on {quotation.approved_at}
                                </p>
                            )}

                            <div className="pt-1 text-[11px] text-slate-500">
                                {can.approve
                                    ? 'You can approve or reject this quotation.'
                                    : quotation.required_approver
                                      ? `This quote is above your approval layer — it needs ${quotation.required_approver} sign-off.`
                                      : 'A manager must sign off quotes above policy thresholds.'}
                            </div>
                        </CardBody>
                    </Card>
                </div>
            </div>

            <Modal
                open={rejectOpen}
                onClose={() => setRejectOpen(false)}
                title="Reject quotation"
                description={`${quotation.quote_number} will be returned to the sales rep with your reason.`}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setRejectOpen(false)}>Cancel</Button>
                        <Button
                            type="submit"
                            form="reject-quotation-form"
                            variant="danger"
                            loading={rejectForm.processing}
                            disabled={!rejectForm.data.reason.trim()}
                        >
                            Reject quotation
                        </Button>
                    </>
                }
            >
                <form id="reject-quotation-form" onSubmit={submitReject} className="space-y-3">
                    <Field label="Reason" required error={rejectForm.errors.reason}>
                        <Textarea
                            value={rejectForm.data.reason}
                            onChange={(event) => rejectForm.setData('reason', event.target.value)}
                            rows={3}
                            placeholder="e.g. Discount exceeds the current quarter's floor…"
                        />
                    </Field>
                </form>
            </Modal>
        </AppLayout>
    );
}
