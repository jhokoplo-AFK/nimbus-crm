import { router, useForm, usePage } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import OpportunityKanban from '../../Components/OpportunityKanban';
import Badge from '../../Components/UI/Badge';
import Button from '../../Components/UI/Button';
import { Field, Input, Select } from '../../Components/UI/Input';
import Modal from '../../Components/UI/Modal';
import { formatMoney } from '../../lib/format';

export default function Kanban({ opportunities = [], columns = [], filters, accounts = [] }) {
    const { enums, auth } = usePage().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [createOpen, setCreateOpen] = useState(false);

    const canManage = (auth?.user?.permissions ?? []).includes('opportunities.manage');
    const totalValue = opportunities.reduce((sum, opportunity) => sum + Number(opportunity.amount || 0), 0);
    const weightedValue = opportunities.reduce((sum, opportunity) => sum + Number(opportunity.weighted_amount || 0), 0);

    const form = useForm({
        account_id: accounts[0]?.id ?? '',
        primary_contact_id: '',
        name: '',
        stage: 'Prospecting',
        amount: '',
        close_date: '',
    });

    const submit = (event) => {
        event.preventDefault();

        form.post('/opportunities', {
            preserveScroll: true,
            onSuccess: () => {
                setCreateOpen(false);
                form.reset();
            },
        });
    };

    return (
        <AppLayout title="Pipeline">
            <div className="space-y-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-slate-900">Opportunity pipeline</h1>
                        <p className="text-sm text-slate-500">
                            {opportunities.length} deals · {formatMoney(totalValue)} open ·{' '}
                            {formatMoney(weightedValue)} weighted
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="relative hidden sm:block">
                            <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        router.get('/opportunities', { search }, { preserveState: true, replace: true });
                                    }
                                }}
                                placeholder="Filter pipeline…"
                                className="w-56 pl-8"
                            />
                        </div>
                        <Button onClick={() => setCreateOpen(true)}>
                            <Plus className="h-4 w-4" />
                            New opportunity
                        </Button>
                    </div>
                </header>

                <div className="flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                    <Badge tone="slate" dot={false}>Drag cards between columns</Badge>
                    <span>or use the stage selector on each card.</span>
                    {!canManage && (
                        <span className="font-medium text-rose-600">You have read-only access to stage changes.</span>
                    )}
                </div>

                <OpportunityKanban opportunities={opportunities} columns={columns} canManage={canManage} />
            </div>

            <Modal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                title="New opportunity"
                description="Add a deal directly to the pipeline."
                width="max-w-xl"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setCreateOpen(false)}>Cancel</Button>
                        <Button type="submit" form="create-opportunity-form" loading={form.processing}>
                            Create opportunity
                        </Button>
                    </>
                }
            >
                <form id="create-opportunity-form" onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
                    <Field label="Account" required error={form.errors.account_id} className="sm:col-span-2">
                        <Select value={form.data.account_id} onChange={(event) => form.setData('account_id', event.target.value)}>
                            <option value="">Select an account…</option>
                            {accounts.map((account) => (
                                <option key={account.id} value={account.id}>{account.name}</option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Opportunity name" required error={form.errors.name} className="sm:col-span-2">
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                    </Field>

                    <Field label="Stage" required error={form.errors.stage}>
                        <Select value={form.data.stage} onChange={(event) => form.setData('stage', event.target.value)}>
                            {(enums?.opportunityStages ?? []).map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Amount" required error={form.errors.amount}>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.amount}
                            onChange={(event) => form.setData('amount', event.target.value)}
                            placeholder="0.00"
                        />
                    </Field>

                    <Field label="Target close date" error={form.errors.close_date}>
                        <Input type="date" value={form.data.close_date} onChange={(event) => form.setData('close_date', event.target.value)} />
                    </Field>

                    <Field label="Loss reason" error={form.errors.loss_reason} hint="Required when closing as lost.">
                        <Input
                            value={form.data.loss_reason ?? ''}
                            onChange={(event) => form.setData('loss_reason', event.target.value)}
                        />
                    </Field>
                </form>
            </Modal>
        </AppLayout>
    );
}
