import { Link, useForm, usePage } from '@inertiajs/react';
import { FilePlus2 } from 'lucide-react';
import { useState } from 'react';
import ActivityTimeline from '../../Components/ActivityTimeline';
import RecordDetailLayout from '../../Components/RecordDetailLayout';
import Badge from '../../Components/UI/Badge';
import Button from '../../Components/UI/Button';
import DataTable from '../../Components/UI/DataTable';
import DeferredSection from '../../Components/UI/DeferredSection';
import { Field, Input, Select } from '../../Components/UI/Input';
import AppLayout from '../../Layouts/AppLayout';
import { formatDate, formatMoney } from '../../lib/format';

export default function Show({
    opportunity,
    activities = [],
    quotations,
    contracts,
    salesOrders,
    pricebooks = [],
    can,
}) {
    const { enums } = usePage().props;
    const [tab, setTab] = useState('details');

    const stageForm = useForm({
        stage: opportunity.stage,
        loss_reason: opportunity.loss_reason ?? '',
    });

    const submitStage = (event) => {
        event.preventDefault();
        stageForm.patch(`/opportunities/${opportunity.id}/stage`, { preserveScroll: true });
    };

    const stageSteps = (enums?.opportunityStages ?? []).map((stage) => ({
        value: stage.value,
        label: stage.label,
    }));

    const quotationCount = (quotations ?? []).length;

    return (
        <AppLayout title={opportunity.name}>
            <RecordDetailLayout
                backHref="/opportunities"
                backLabel="Pipeline board"
                title={opportunity.name}
                subtitle={`${opportunity.account?.name ?? 'No account'} · Opportunity`}
                badges={
                    <>
                        <Badge tone={opportunity.stage_tone}>{opportunity.stage_label}</Badge>
                        <Badge tone="slate" dot={false}>{opportunity.probability_pct}% probability</Badge>
                    </>
                }
                stage={{ current: opportunity.stage, steps: stageSteps }}
                actions={
                    can.createQuotation ? (
                        <Link href={`/quotations/create?opportunity=${opportunity.id}`}>
                            <Button>
                                <FilePlus2 className="h-4 w-4" />
                                New quotation
                            </Button>
                        </Link>
                    ) : null
                }
                highlights={[
                    { label: 'Amount', value: formatMoney(opportunity.amount) },
                    { label: 'Weighted', value: formatMoney(opportunity.weighted_amount) },
                    { label: 'Close date', value: formatDate(opportunity.close_date) },
                    { label: 'Owner contact', value: opportunity.primary_contact?.name ?? '—' },
                ]}
                tabs={[
                    { key: 'details', label: 'Details' },
                    { key: 'quotations', label: 'Quotations', count: quotationCount },
                    { key: 'contracts', label: 'Contracts' },
                    { key: 'orders', label: 'Sales orders' },
                ]}
                activeTab={tab}
                onTabChange={setTab}
                activityPanel={
                    <ActivityTimeline
                        title="Opportunity activity"
                        activities={activities}
                        relatableType="opportunity"
                        relatableId={opportunity.id}
                    />
                }
            >
                {tab === 'details' && (
                    <div className="space-y-5">
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Account</dt>
                                <dd className="mt-0.5 text-sm">
                                    {opportunity.account ? (
                                        <Link href={`/accounts/${opportunity.account.id}`} className="font-medium text-brand-700 hover:underline">
                                            {opportunity.account.name}
                                        </Link>
                                    ) : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Primary contact</dt>
                                <dd className="mt-0.5 text-sm text-slate-800">
                                    {opportunity.primary_contact ? (
                                        <>
                                            <p className="font-medium">{opportunity.primary_contact.name}</p>
                                            <p className="text-xs text-slate-500">
                                                {opportunity.primary_contact.email ?? '—'} · {opportunity.primary_contact.phone ?? '—'}
                                            </p>
                                        </>
                                    ) : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Stage</dt>
                                <dd className="mt-0.5 text-sm text-slate-800">{opportunity.stage_label}</dd>
                            </div>
                            <div>
                                <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Created</dt>
                                <dd className="mt-0.5 text-sm text-slate-800">{opportunity.created_at}</dd>
                            </div>
                            {opportunity.loss_reason && (
                                <div className="sm:col-span-2">
                                    <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Loss reason</dt>
                                    <dd className="mt-0.5 text-sm text-rose-700">{opportunity.loss_reason}</dd>
                                </div>
                            )}
                        </dl>

                        {can.updateStage && (
                            <form onSubmit={submitStage} className="rounded-md border border-slate-200 bg-slate-50 p-3">
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Move stage</p>
                                <div className="flex flex-wrap items-end gap-2">
                                    <Field className="w-48" error={stageForm.errors.stage}>
                                        <Select
                                            value={stageForm.data.stage}
                                            onChange={(event) => stageForm.setData('stage', event.target.value)}
                                        >
                                            {(enums?.opportunityStages ?? []).map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </Select>
                                    </Field>

                                    {stageForm.data.stage === 'Closed Lost' && (
                                        <Field className="min-w-[220px] flex-1" error={stageForm.errors.loss_reason}>
                                            <Input
                                                value={stageForm.data.loss_reason}
                                                onChange={(event) => stageForm.setData('loss_reason', event.target.value)}
                                                placeholder="Loss reason (required)"
                                            />
                                        </Field>
                                    )}

                                    <Button type="submit" variant="secondary" loading={stageForm.processing}>
                                        Update stage
                                    </Button>
                                </div>
                                {stageForm.data.stage === 'Closed Won' && (
                                    <p className="mt-2 text-[11px] text-emerald-700">
                                        Closing as won locks probability at 100% and creates a Draft sales order for the ERP.
                                    </p>
                                )}
                            </form>
                        )}
                    </div>
                )}

                {tab === 'quotations' && (
                    <DeferredSection data="quotations" rows={3}>
                        {() => (
                            <DataTable
                                columns={[
                                    {
                                        key: 'quote_number',
                                        header: 'Quote',
                                        render: (row) => (
                                            <Link href={`/quotations/${row.id}`} className="font-medium text-brand-700 hover:underline">
                                                {row.quote_number}
                                            </Link>
                                        ),
                                    },
                                    { key: 'revision_number', header: 'Rev', render: (row) => `R${row.revision_number}` },
                                    { key: 'status', header: 'Status', render: (row) => <Badge tone={row.status_tone}>{row.status}</Badge> },
                                    { key: 'pricebook', header: 'Pricebook', render: (row) => <span className="text-xs text-slate-600">{row.pricebook ?? '—'}</span> },
                                    { key: 'grand_total', header: 'Total', align: 'right', render: (row) => formatMoney(row.grand_total) },
                                    { key: 'expiration_date', header: 'Expires', render: (row) => formatDate(row.expiration_date) },
                                ]}
                                rows={quotations ?? []}
                                emptyMessage="No quotations yet. Create one to start pricing."
                            />
                        )}
                    </DeferredSection>
                )}

                {tab === 'contracts' && (
                    <DeferredSection data="contracts" rows={2}>
                        {() => (
                            <DataTable
                                columns={[
                                    { key: 'contract_number', header: 'Contract', render: (row) => <span className="font-medium text-slate-900">{row.contract_number}</span> },
                                    { key: 'status', header: 'Status', render: (row) => <Badge tone={row.status_tone}>{row.status}</Badge> },
                                    { key: 'start_date', header: 'Start', render: (row) => formatDate(row.start_date) },
                                    { key: 'end_date', header: 'End', render: (row) => formatDate(row.end_date) },
                                ]}
                                rows={contracts ?? []}
                                emptyMessage="No contracts linked to this opportunity."
                            />
                        )}
                    </DeferredSection>
                )}

                {tab === 'orders' && (
                    <DeferredSection data="salesOrders" rows={2}>
                        {() => (
                            <DataTable
                                columns={[
                                    { key: 'order_number', header: 'Order', render: (row) => <span className="font-medium text-slate-900">{row.order_number}</span> },
                                    { key: 'status', header: 'ERP status', render: (row) => <Badge tone={row.status_tone}>{row.status_label}</Badge> },
                                    { key: 'order_date', header: 'Date', render: (row) => formatDate(row.order_date) },
                                    { key: 'total_amount', header: 'Total', align: 'right', render: (row) => formatMoney(row.total_amount) },
                                ]}
                                rows={salesOrders ?? []}
                                emptyMessage="No sales orders yet. Closing this deal as won creates one automatically."
                            />
                        )}
                    </DeferredSection>
                )}
            </RecordDetailLayout>
        </AppLayout>
    );
}
