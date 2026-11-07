import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Badge from '../../Components/UI/Badge';
import Button from '../../Components/UI/Button';
import Card from '../../Components/UI/Card';
import DataTable from '../../Components/UI/DataTable';
import { Select } from '../../Components/UI/Input';
import Pagination from '../../Components/UI/Pagination';
import { formatDate } from '../../lib/format';

export default function Index({ contracts, filters, statuses = [] }) {
    const [status, setStatus] = useState(filters.status ?? '');

    const columns = [
        {
            key: 'contract_number',
            header: 'Contract',
            render: (contract) => <span className="font-medium text-slate-900">{contract.contract_number}</span>,
        },
        {
            key: 'account',
            header: 'Account',
            render: (contract) =>
                contract.account ? (
                    <Link href={`/accounts/${contract.account.id}`} className="text-sm text-brand-700 hover:underline">
                        {contract.account.name}
                    </Link>
                ) : '—',
        },
        {
            key: 'opportunity',
            header: 'Opportunity',
            render: (contract) =>
                contract.opportunity ? (
                    <Link href={`/opportunities/${contract.opportunity.id}`} className="text-xs text-slate-600 hover:text-brand-700">
                        {contract.opportunity.name}
                    </Link>
                ) : '—',
        },
        { key: 'status', header: 'Status', render: (contract) => <Badge tone={contract.status_tone}>{contract.status}</Badge> },
        { key: 'start_date', header: 'Start', render: (contract) => <span className="text-xs text-slate-600">{formatDate(contract.start_date)}</span> },
        { key: 'end_date', header: 'End', render: (contract) => <span className="text-xs text-slate-600">{formatDate(contract.end_date)}</span> },
        { key: 'sales_orders_count', header: 'Orders', align: 'right' },
    ];

    return (
        <AppLayout title="Contracts">
            <div className="space-y-4">
                <header>
                    <h1 className="text-xl font-semibold tracking-tight text-slate-900">Contracts</h1>
                    <p className="text-sm text-slate-500">Signed agreements that anchor recurring revenue.</p>
                </header>

                <Card>
                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-3">
                        <Select
                            value={status}
                            onChange={(event) => {
                                setStatus(event.target.value);
                                router.get('/contracts', event.target.value ? { status: event.target.value } : {}, {
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
                        <span className="ml-auto text-xs text-slate-500">{contracts.total} contracts</span>
                    </div>

                    <DataTable columns={columns} rows={contracts.data} emptyMessage="No contracts found." />
                    <Pagination paginator={contracts} />
                </Card>

                <p className="text-xs text-slate-500">
                    Contracts are created when a negotiated deal is ready for signature; the Closed-Won automation then
                    links the generated sales order to the contract.
                </p>
            </div>
        </AppLayout>
    );
}
