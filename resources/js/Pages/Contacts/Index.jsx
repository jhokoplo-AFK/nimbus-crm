import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Badge from '../../Components/UI/Badge';
import Button from '../../Components/UI/Button';
import Card from '../../Components/UI/Card';
import DataTable from '../../Components/UI/DataTable';
import { Input } from '../../Components/UI/Input';
import Pagination from '../../Components/UI/Pagination';

export default function Index({ contacts, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    const columns = [
        {
            key: 'name',
            header: 'Contact',
            render: (contact) => (
                <div className="min-w-0">
                    <p className="flex items-center gap-1.5 font-medium text-slate-900">
                        {contact.name}
                        {contact.is_primary && <Badge tone="amber" dot={false}>Primary</Badge>}
                    </p>
                    <p className="truncate text-xs text-slate-500">{contact.title || '—'}</p>
                </div>
            ),
        },
        {
            key: 'account',
            header: 'Account',
            render: (contact) =>
                contact.account ? (
                    <Link href={`/accounts/${contact.account.id}`} className="text-xs font-medium text-brand-700 hover:underline">
                        {contact.account.name}
                    </Link>
                ) : (
                    <span className="text-xs text-slate-400">—</span>
                ),
        },
        { key: 'email', header: 'Email', render: (contact) => <span className="text-xs text-slate-600">{contact.email || '—'}</span> },
        { key: 'phone', header: 'Phone', render: (contact) => <span className="text-xs text-slate-600">{contact.phone || '—'}</span> },
    ];

    return (
        <AppLayout title="Contacts">
            <div className="space-y-4">
                <header>
                    <h1 className="text-xl font-semibold tracking-tight text-slate-900">Contacts</h1>
                    <p className="text-sm text-slate-500">Every buyer, champion and gatekeeper across your accounts.</p>
                </header>

                <Card>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get('/contacts', { search }, { preserveState: true, replace: true });
                        }}
                        className="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-3"
                    >
                        <div className="relative min-w-[220px] flex-1">
                            <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search contacts or accounts…"
                                className="pl-8"
                            />
                        </div>
                        <Button type="submit" variant="secondary">Search</Button>
                    </form>

                    <DataTable columns={columns} rows={contacts.data} emptyMessage="No contacts found." />
                    <Pagination paginator={contacts} />
                </Card>
            </div>
        </AppLayout>
    );
}
