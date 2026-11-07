import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Button from '../../Components/UI/Button';
import Card from '../../Components/UI/Card';
import DataTable from '../../Components/UI/DataTable';
import { Input, Select } from '../../Components/UI/Input';
import Pagination from '../../Components/UI/Pagination';
import { formatMoney, formatNumber } from '../../lib/format';

export default function Index({ accounts, filters, industries = [] }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [industry, setIndustry] = useState(filters.industry ?? '');

    const apply = (next = {}) => {
        router.get('/accounts', { search, industry, ...next }, { preserveState: true, replace: true });
    };

    const columns = [
        {
            key: 'name',
            header: 'Account',
            render: (account) => (
                <div className="min-w-0">
                    <Link href={`/accounts/${account.id}`} className="font-medium text-slate-900 hover:text-brand-700">
                        {account.name}
                    </Link>
                    <p className="truncate text-xs text-slate-500">{account.website || '—'}</p>
                </div>
            ),
        },
        { key: 'industry', header: 'Industry', render: (account) => <span className="text-xs text-slate-600">{account.industry || '—'}</span> },
        {
            key: 'annual_revenue',
            header: 'Annual revenue',
            align: 'right',
            render: (account) => <span className="text-xs text-slate-700">{formatMoney(account.annual_revenue)}</span>,
        },
        { key: 'contacts_count', header: 'Contacts', align: 'right' },
        { key: 'opportunities_count', header: 'Deals', align: 'right' },
        {
            key: 'open_pipeline_value',
            header: 'Open pipeline',
            align: 'right',
            render: (account) => (
                <span className="text-xs font-semibold text-slate-900">{formatMoney(account.open_pipeline_value)}</span>
            ),
        },
    ];

    return (
        <AppLayout title="Accounts">
            <div className="space-y-4">
                <header>
                    <h1 className="text-xl font-semibold tracking-tight text-slate-900">Accounts</h1>
                    <p className="text-sm text-slate-500">
                        {formatNumber(accounts.total)} companies in the revenue book.
                    </p>
                </header>

                <Card>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply();
                        }}
                        className="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-3"
                    >
                        <div className="relative min-w-[220px] flex-1">
                            <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search accounts…"
                                className="pl-8"
                            />
                        </div>
                        <Select value={industry} onChange={(event) => setIndustry(event.target.value)} className="w-44">
                            <option value="">All industries</option>
                            {industries.map((item) => (
                                <option key={item} value={item}>{item}</option>
                            ))}
                        </Select>
                        <Button type="submit" variant="secondary">Apply</Button>
                    </form>

                    <DataTable columns={columns} rows={accounts.data} emptyMessage="No accounts found." />
                    <Pagination paginator={accounts} />
                </Card>
            </div>
        </AppLayout>
    );
}
