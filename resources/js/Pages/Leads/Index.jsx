import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Badge from '../../Components/UI/Badge';
import Button from '../../Components/UI/Button';
import Card from '../../Components/UI/Card';
import ConvertLeadModal from '../../Components/ConvertLeadModal';
import DataTable from '../../Components/UI/DataTable';
import { Field, Input, Select, Textarea } from '../../Components/UI/Input';
import Modal from '../../Components/UI/Modal';
import Pagination from '../../Components/UI/Pagination';
import { cn, formatDate, formatNumber } from '../../lib/format';

const EMPTY_LEAD = {
    first_name: '',
    last_name: '',
    company: '',
    title: '',
    email: '',
    phone: '',
    lead_source: '',
    status: 'New',
    notes: '',
};

export default function Index({ leads, filters, statusCounts = {}, sources = [] }) {
    const { enums } = usePage().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [convertTarget, setConvertTarget] = useState(null);

    const form = useForm({ ...EMPTY_LEAD });

    const applyFilters = (next = {}) => {
        router.get(
            '/leads',
            { search, status, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitCreate = (event) => {
        event.preventDefault();

        form.post('/leads', {
            preserveScroll: true,
            onSuccess: () => {
                setCreateOpen(false);
                form.reset();
            },
        });
    };

    const statusOptions = (enums?.leadStatuses ?? []).filter((option) => option.value !== 'Converted');

    const columns = [
        {
            key: 'name',
            header: 'Lead',
            render: (lead) => (
                <div className="min-w-0">
                    <Link href={`/leads/${lead.id}`} className="font-medium text-slate-900 hover:text-brand-700">
                        {lead.first_name} {lead.last_name}
                    </Link>
                    <p className="truncate text-xs text-slate-500">{lead.company || '—'}</p>
                </div>
            ),
        },
        { key: 'email', header: 'Email', render: (lead) => <span className="text-xs text-slate-600">{lead.email || '—'}</span> },
        { key: 'lead_source', header: 'Source', render: (lead) => <span className="text-xs text-slate-600">{lead.lead_source || '—'}</span> },
        {
            key: 'status',
            header: 'Status',
            render: (lead) => <Badge tone={lead.status_tone}>{lead.status_label}</Badge>,
        },
        { key: 'created_at', header: 'Created', render: (lead) => <span className="text-xs text-slate-500">{formatDate(lead.created_at)}</span> },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (lead) =>
                lead.can_convert ? (
                    <Button size="sm" variant="secondary" onClick={() => setConvertTarget(lead)}>
                        Convert
                    </Button>
                ) : lead.is_locked ? (
                    <span className="text-[11px] font-medium uppercase tracking-wide text-violet-600">Converted</span>
                ) : null,
        },
    ];

    return (
        <AppLayout title="Leads">
            <div className="space-y-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-slate-900">Leads</h1>
                        <p className="text-sm text-slate-500">Qualify inbound demand and convert it into pipeline.</p>
                    </div>
                    <Button onClick={() => setCreateOpen(true)}>
                        <Plus className="h-4 w-4" />
                        New lead
                    </Button>
                </header>

                <div className="flex flex-wrap items-center gap-1.5">
                    <button
                        type="button"
                        onClick={() => {
                            setStatus('');
                            applyFilters({ status: '' });
                        }}
                        className={cn(
                            'rounded-full px-3 py-1.5 text-xs font-medium transition',
                            status === '' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50',
                        )}
                    >
                        All leads
                    </button>
                    {(enums?.leadStatuses ?? []).map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            onClick={() => {
                                setStatus(option.value);
                                applyFilters({ status: option.value });
                            }}
                            className={cn(
                                'rounded-full px-3 py-1.5 text-xs font-medium transition',
                                status === option.value
                                    ? 'bg-slate-900 text-white'
                                    : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50',
                            )}
                        >
                            {option.label}
                            <span className="ml-1.5 text-[10px] opacity-70">
                                {formatNumber(Number(statusCounts[option.value] ?? 0))}
                            </span>
                        </button>
                    ))}
                </div>

                <Card>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilters();
                        }}
                        className="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-3"
                    >
                        <div className="relative min-w-[220px] flex-1">
                            <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search name, company, email…"
                                className="pl-8"
                            />
                        </div>
                        <Select value={status} onChange={(event) => setStatus(event.target.value)} className="w-40">
                            <option value="">All statuses</option>
                            {(enums?.leadStatuses ?? []).map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                        <Button type="submit" variant="secondary">Apply</Button>
                    </form>

                    <DataTable columns={columns} rows={leads.data} emptyMessage="No leads match these filters." />
                    <Pagination paginator={leads} />
                </Card>
            </div>

            <Modal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                title="New lead"
                description="Capture the enquiry before it reaches the pipeline."
                width="max-w-2xl"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setCreateOpen(false)}>Cancel</Button>
                        <Button type="submit" form="create-lead-form" loading={form.processing}>Create lead</Button>
                    </>
                }
            >
                <form id="create-lead-form" onSubmit={submitCreate} className="grid gap-3 sm:grid-cols-2">
                    <Field label="First name" required error={form.errors.first_name}>
                        <Input value={form.data.first_name} onChange={(event) => form.setData('first_name', event.target.value)} />
                    </Field>
                    <Field label="Last name" required error={form.errors.last_name}>
                        <Input value={form.data.last_name} onChange={(event) => form.setData('last_name', event.target.value)} />
                    </Field>
                    <Field label="Company" error={form.errors.company}>
                        <Input value={form.data.company} onChange={(event) => form.setData('company', event.target.value)} />
                    </Field>
                    <Field label="Title" error={form.errors.title}>
                        <Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
                    </Field>
                    <Field label="Email" error={form.errors.email}>
                        <Input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                    </Field>
                    <Field label="Phone" error={form.errors.phone}>
                        <Input value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                    </Field>
                    <Field label="Lead source" htmlFor="lead_source" error={form.errors.lead_source}>
                        <Input
                            id="lead_source"
                            list="lead-sources"
                            value={form.data.lead_source}
                            onChange={(event) => form.setData('lead_source', event.target.value)}
                            placeholder="Web, Referral, Event…"
                        />
                        <datalist id="lead-sources">
                            {(sources ?? []).map((source) => (
                                <option key={source} value={source} />
                            ))}
                        </datalist>
                    </Field>
                    <Field label="Status" required error={form.errors.status}>
                        <Select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Notes" className="sm:col-span-2" error={form.errors.notes}>
                        <Textarea value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} rows={3} />
                    </Field>
                </form>
            </Modal>

            {convertTarget && (
                <ConvertLeadModal lead={convertTarget} open onClose={() => setConvertTarget(null)} />
            )}
        </AppLayout>
    );
}
