import { Link, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Badge from '../../Components/UI/Badge';
import Card from '../../Components/UI/Card';
import DataTable from '../../Components/UI/DataTable';
import { Select } from '../../Components/UI/Input';
import Pagination from '../../Components/UI/Pagination';
import { formatDate, formatMoney } from '../../lib/format';

export default function Index({ orders, filters, statuses = [], pendingErp = 0 }) {
    const [status, setStatus] = useState(filters.status ?? '');

    const columns = [
        {
            key: 'order_number',
            header: 'Sales order',
            render: (order) => (
                <div>
                    <p className="font-medium text-slate-900">{order.order_number}</p>
                    <p className="text-[11px] text-slate-500">{formatDate(order.order_date)}</p>
                </div>
            ),
        },
        {
            key: 'account',
            header: 'Account',
            render: (order) =>
                order.account ? (
                    <Link href={`/accounts/${order.account.id}`} className="text-sm text-brand-700 hover:underline">
                        {order.account.name}
                    </Link>
                ) : '—',
        },
        {
            key: 'opportunity',
            header: 'Opportunity',
            render: (order) =>
                order.opportunity ? (
                    <Link href={`/opportunities/${order.opportunity.id}`} className="text-xs text-slate-600 hover:text-brand-700">
                        {order.opportunity.name}
                    </Link>
                ) : '—',
        },
        { key: 'contract_number', header: 'Contract', render: (order) => <span className="text-xs text-slate-600">{order.contract_number ?? '—'}</span> },
        { key: 'status', header: 'ERP status', render: (order) => <Badge tone={order.status_tone}>{order.status_label}</Badge> },
        { key: 'total_amount', header: 'Total', align: 'right', render: (order) => <span className="font-semibold text-slate-900">{formatMoney(order.total_amount)}</span> },
    ];

    return (
        <AppLayout title="Sales orders">
            <div className="space-y-4">
                <header>
                    <h1 className="text-xl font-semibold tracking-tight text-slate-900">Sales orders</h1>
                    <p className="text-sm text-slate-500">
                        Closed-Won deals land here as Draft orders, ready for the SAP Business One connector.
                    </p>
                </header>

                {pendingErp > 0 && (
                    <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <RefreshCw className="mt-0.5 h-4 w-4 shrink-0" />
                        <p>
                            <strong>{pendingErp}</strong> draft order{pendingErp === 1 ? '' : 's'} pending ERP sync. Confirm
                            the order once the external system acknowledges it.
                        </p>
                    </div>
                )}

                <Card>
                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-3">
                        <Select
                            value={status}
                            onChange={(event) => {
                                setStatus(event.target.value);
                                router.get('/sales-orders', event.target.value ? { status: event.target.value } : {}, {
                                    preserveState: true,
                                    replace: true,
                                });
                            }}
                            className="w-44"
                        >
                            <option value="">All statuses</option>
                            {statuses.map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </Select>
                        <span className="ml-auto text-xs text-slate-500">{orders.total} orders</span>
                    </div>

                    <DataTable columns={columns} rows={orders.data} emptyMessage="No sales orders yet." />
                    <Pagination paginator={orders} />
                </Card>
            </div>
        </AppLayout>
    );
}
