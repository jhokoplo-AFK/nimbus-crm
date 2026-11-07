import { useForm } from '@inertiajs/react';
import { ArrowRight, Building2, Target, User } from 'lucide-react';
import { formatMoney } from '../lib/format';
import Modal from './UI/Modal';
import Button from './UI/Button';
import { Field, Input } from './UI/Input';

function PreviewCard({ icon: Icon, label, title, meta }) {
    return (
        <div className="rounded-md border border-slate-200 bg-slate-50/60 p-3">
            <p className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                <Icon className="h-3 w-3" />
                {label}
            </p>
            <p className="mt-1 truncate text-sm font-semibold text-slate-900">{title}</p>
            {meta && <p className="truncate text-xs text-slate-500">{meta}</p>}
        </div>
    );
}

export default function ConvertLeadModal({ lead, open, onClose }) {
    const defaultAccountName = lead.company || `${lead.first_name} ${lead.last_name}`;

    const { data, setData, post, processing, errors, reset } = useForm({
        account_name: defaultAccountName,
        industry: '',
        website: '',
        opportunity_name: `${defaultAccountName} - New Opportunity`,
        amount: '',
        close_date: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(`/leads/${lead.id}/convert`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Convert lead"
            description="This creates an account, a primary contact and an open opportunity."
            width="max-w-2xl"
            footer={
                <>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="convert-lead-form" loading={processing}>
                        Convert lead
                        <ArrowRight className="h-3.5 w-3.5" />
                    </Button>
                </>
            }
        >
            <form id="convert-lead-form" onSubmit={submit} className="space-y-4">
                <div className="flex items-center gap-2 text-xs text-slate-500">
                    <span className="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-600">Lead</span>
                    <ArrowRight className="h-3 w-3" />
                    <span className="font-medium text-slate-700">{lead.first_name} {lead.last_name}</span>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <PreviewCard
                        icon={Building2}
                        label="Account"
                        title={data.account_name || defaultAccountName}
                        meta={lead.company ? 'Created from lead company' : 'Created from lead name'}
                    />
                    <PreviewCard
                        icon={User}
                        label="Contact"
                        title={`${lead.first_name} ${lead.last_name}`}
                        meta={lead.email || lead.phone || 'No contact details'}
                    />
                    <PreviewCard
                        icon={Target}
                        label="Opportunity"
                        title={data.opportunity_name || 'New opportunity'}
                        meta={data.amount ? formatMoney(data.amount) : 'Amount to be qualified'}
                    />
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="Account name" htmlFor="account_name" error={errors.account_name}>
                        <Input
                            id="account_name"
                            value={data.account_name}
                            onChange={(event) => setData('account_name', event.target.value)}
                        />
                    </Field>

                    <Field label="Industry" htmlFor="industry" error={errors.industry}>
                        <Input
                            id="industry"
                            value={data.industry}
                            onChange={(event) => setData('industry', event.target.value)}
                            placeholder="e.g. Manufacturing"
                        />
                    </Field>

                    <Field label="Website" htmlFor="website" error={errors.website}>
                        <Input
                            id="website"
                            value={data.website}
                            onChange={(event) => setData('website', event.target.value)}
                            placeholder="https://"
                        />
                    </Field>

                    <Field label="Opportunity name" htmlFor="opportunity_name" error={errors.opportunity_name}>
                        <Input
                            id="opportunity_name"
                            value={data.opportunity_name}
                            onChange={(event) => setData('opportunity_name', event.target.value)}
                        />
                    </Field>

                    <Field label="Estimated amount" htmlFor="amount" error={errors.amount}>
                        <Input
                            id="amount"
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.amount}
                            onChange={(event) => setData('amount', event.target.value)}
                            placeholder="0.00"
                        />
                    </Field>

                    <Field label="Target close date" htmlFor="close_date" error={errors.close_date}>
                        <Input
                            id="close_date"
                            type="date"
                            value={data.close_date}
                            onChange={(event) => setData('close_date', event.target.value)}
                        />
                    </Field>
                </div>

                <p className="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    The lead will be marked <strong>Converted</strong> and locked for editing. This action cannot be
                    undone.
                </p>
            </form>
        </Modal>
    );
}
