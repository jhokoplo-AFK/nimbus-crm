import { Link } from '@inertiajs/react';
import { Mail, Phone, Star } from 'lucide-react';
import { useState } from 'react';
import ActivityTimeline from '../../Components/ActivityTimeline';
import RecordDetailLayout from '../../Components/RecordDetailLayout';
import Badge from '../../Components/UI/Badge';
import Card from '../../Components/UI/Card';
import DataTable from '../../Components/UI/DataTable';
import DeferredSection from '../../Components/UI/DeferredSection';
import AppLayout from '../../Layouts/AppLayout';
import { formatDate, formatMoney } from '../../lib/format';

export default function Show({ account, contacts = [], opportunities, contracts, salesOrders, activities = [] }) {
    const [tab, setTab] = useState('overview');

    return (
        <AppLayout title={account.name}>
            <RecordDetailLayout
                backHref="/accounts"
                backLabel="All accounts"
                title={account.name}
                subtitle={account.industry ? `${account.industry} · Account` : 'Account'}
                badges={<Badge tone="slate" dot={false}>Customer account</Badge>}
                highlights={[
                    { label: 'Annual revenue', value: formatMoney(account.annual_revenue) },
                    { label: 'Open pipeline', value: formatMoney(account.open_pipeline_value) },
                    { label: 'Contacts', value: account.contacts_count },
                    { label: 'Customer since', value: account.created_at },
                ]}
                tabs={[
                    { key: 'overview', label: 'Overview' },
                    { key: 'contacts', label: 'Contacts', count: contacts.length },
                    { key: 'opportunities', label: 'Opportunities' },
                    { key: 'contracts', label: 'Contracts' },
                    { key: 'orders', label: 'Sales orders' },
                ]}
                activeTab={tab}
                onTabChange={setTab}
                activityPanel={
                    <ActivityTimeline
                        title="Account activity"
                        activities={activities}
                        relatableType="account"
                        relatableId={account.id}
                    />
                }
            >
                {tab === 'overview' && (
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Industry</dt>
                            <dd className="mt-0.5 text-sm text-slate-800">{account.industry || '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Phone</dt>
                            <dd className="mt-0.5 text-sm text-slate-800">
                                {account.phone ? (
                                    <a href={`tel:${account.phone}`} className="inline-flex items-center gap-1 text-brand-700 hover:underline">
                                        <Phone className="h-3.5 w-3.5" />
                                        {account.phone}
                                    </a>
                                ) : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Website</dt>
                            <dd className="mt-0.5 truncate text-sm text-slate-800">
                                {account.website ? (
                                    <a href={account.website} target="_blank" rel="noreferrer" className="text-brand-700 hover:underline">
                                        {account.website}
                                    </a>
                                ) : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Billing address</dt>
                            <dd className="mt-0.5 whitespace-pre-line text-sm text-slate-800">{account.billing_address || '—'}</dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Shipping address</dt>
                            <dd className="mt-0.5 whitespace-pre-line text-sm text-slate-800">{account.shipping_address || '—'}</dd>
                        </div>
                    </dl>
                )}

                {tab === 'contacts' && (
                    <ul className="divide-y divide-slate-100">
                        {contacts.length === 0 && (
                            <li className="py-6 text-center text-sm text-slate-500">No contacts on this account.</li>
                        )}
                        {contacts.map((contact) => (
                            <li key={contact.id} className="flex items-start justify-between gap-3 py-3">
                                <div className="min-w-0">
                                    <p className="flex items-center gap-1.5 text-sm font-medium text-slate-900">
                                        {contact.name}
                                        {contact.is_primary && (
                                            <span className="inline-flex items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                                                <Star className="h-2.5 w-2.5" />
                                                Primary
                                            </span>
                                        )}
                                    </p>
                                    <p className="text-xs text-slate-500">{contact.title || '—'}</p>
                                </div>
                                <div className="shrink-0 space-y-0.5 text-right text-xs text-slate-600">
                                    {contact.email && (
                                        <a href={`mailto:${contact.email}`} className="flex items-center justify-end gap-1 hover:text-brand-700">
                                            <Mail className="h-3 w-3" />
                                            {contact.email}
                                        </a>
                                    )}
                                    {contact.phone && <p>{contact.phone}</p>}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {tab === 'opportunities' && (
                    <DeferredSection data="opportunities" rows={3}>
                        {() => (
                            <DataTable
                                columns={[
                                    {
                                        key: 'name',
                                        header: 'Opportunity',
                                        render: (row) => (
                                            <Link href={`/opportunities/${row.id}`} className="font-medium text-slate-900 hover:text-brand-700">
                                                {row.name}
                                            </Link>
                                        ),
                                    },
                                    { key: 'stage', header: 'Stage', render: (row) => <Badge tone={row.stage_tone}>{row.stage}</Badge> },
                                    { key: 'amount', header: 'Amount', align: 'right', render: (row) => formatMoney(row.amount) },
                                    { key: 'close_date', header: 'Close date', render: (row) => formatDate(row.close_date) },
                                ]}
                                rows={opportunities ?? []}
                                emptyMessage="No opportunities for this account."
                            />
                        )}
                    </DeferredSection>
                )}

                {tab === 'contracts' && (
                    <DeferredSection data="contracts" rows={3}>
                        {() => (
                            <DataTable
                                columns={[
                                    { key: 'contract_number', header: 'Contract', render: (row) => <span className="font-medium text-slate-900">{row.contract_number}</span> },
                                    { key: 'status', header: 'Status', render: (row) => <Badge tone={row.status_tone}>{row.status}</Badge> },
                                    { key: 'start_date', header: 'Start', render: (row) => formatDate(row.start_date) },
                                    { key: 'end_date', header: 'End', render: (row) => formatDate(row.end_date) },
                                ]}
                                rows={contracts ?? []}
                                emptyMessage="No contracts for this account."
                            />
                        )}
                    </DeferredSection>
                )}

                {tab === 'orders' && (
                    <DeferredSection data="salesOrders" rows={3}>
                        {() => (
                            <DataTable
                                columns={[
                                    { key: 'order_number', header: 'Order', render: (row) => <span className="font-medium text-slate-900">{row.order_number}</span> },
                                    { key: 'status', header: 'ERP status', render: (row) => <Badge tone={row.status_tone}>{row.status_label}</Badge> },
                                    { key: 'order_date', header: 'Date', render: (row) => formatDate(row.order_date) },
                                    { key: 'total_amount', header: 'Total', align: 'right', render: (row) => formatMoney(row.total_amount) },
                                ]}
                                rows={salesOrders ?? []}
                                emptyMessage="No sales orders for this account."
                            />
                        )}
                    </DeferredSection>
                )}
            </RecordDetailLayout>

            <Card className="mt-4">
                <p className="px-4 py-3 text-xs text-slate-500">
                    Closed-Won opportunities automatically produce Draft sales orders here, ready for the ERP connector.
                </p>
            </Card>
        </AppLayout>
    );
}
