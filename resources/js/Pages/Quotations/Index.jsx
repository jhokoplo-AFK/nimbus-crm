import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Badge from '../../Components/UI/Badge';
import Button from '../../Components/UI/Button';
import Card from '../../Components/UI/Card';
import DataTable from '../../Components/UI/DataTable';
import { Input, Select } from '../../Components/UI/Input';
import Pagination from '../../Components/UI/Pagination';
import { formatDate, formatMoney } from '../../lib/format';

export default function Index({ quotations, filters, statuses = [] }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const columns = [
        {
            key: 'quote_number',
            header: 'Quotation',
            render: (quotation) => (
                <div className="min-w-0">
                    <Link href={`/quotations/${quotation.id}`} className="font-medium text-brand-700 hover:underline">
                        {quotation.quote_number}
                    </Link>
                    <p className="text-xs text-slate-500">
                        Revision R{quotation.revision_number}
                        {quotation.is_primary ? ' · Primary' : ''}
                    </p>
                </div>
            ),
        },
        {
            key: 'opportunity',
            header: 'Opportunity',
            render: (quotation) => (
                <div className="min-w-0">
                    <p className="truncate text-sm text-slate-800">{quotation.opportunity ?? '—'}</p>
                    <p className="truncate text-xs text-slate-500">{quotation.account ?? '—'}</p>
                </div>
            ),
        },
        { key: 'pricebook', header: 'Pricebook', render: (quotation) => <span className="text-xs text-slate-600">{quotation.pricebook ?? '—'}</span> },
        { key: 'status', header: 'Status', render: (quotation) => <Badge tone={quotation.status_tone}>{quotation.status}</Badge> },
        { key: 'grand_total', header: 'Grand total', align: 'right', render: (quotation) => <span className="font-semibold text-slate-900">{formatMoney(quotation.grand_total)}</span> },
        { key: 'expiration_date', header: 'Expires', render: (quotation) => <span className="text-xs text-slate-500">{formatDate(quotation.expiration_date)}</span> },
    ];

    return (
        <AppLayout title="Quotations">
            <div className="space-y-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-slate-900">Quotations</h1>
                        <p className="text-sm text-slate-500">Pricebook-backed quotes with revision and approval trails.</p>
                    </div>
                    <Link href="/quotations/create">
                        <Button>New quotation</Button>
                    </Link>
                </header>

                <Card>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get('/quotations', { search, status }, { preserveState: true, replace: true });
                        }}
                        className="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-3"
                    >
                        <div className="relative min-w-[220px] flex-1">
                            <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search quote number or opportunity…"
                                className="pl-8"
                            />
                        </div>
                        <Select value={status} onChange={(event) => setStatus(event.target.value)} className="w-40">
                            <option value="">All statuses</option>
                            {statuses.map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </Select>
                        <Button type="submit" variant="secondary">Apply</Button>
                    </form>

                    <DataTable columns={columns} rows={quotations.data} emptyMessage="No quotations match these filters." />
                    <Pagination paginator={quotations} />
                </Card>
            </div>
        </AppLayout>
    );
}
