import { Link } from '@inertiajs/react';
import { ArrowRight, Building2, Mail, Phone } from 'lucide-react';
import { useState } from 'react';
import ActivityTimeline from '../../Components/ActivityTimeline';
import ConvertLeadModal from '../../Components/ConvertLeadModal';
import RecordDetailLayout from '../../Components/RecordDetailLayout';
import Badge from '../../Components/UI/Badge';
import Button from '../../Components/UI/Button';
import AppLayout from '../../Layouts/AppLayout';

function DetailItem({ label, value }) {
    return (
        <div>
            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className="mt-0.5 text-sm text-slate-800">{value || '—'}</dd>
        </div>
    );
}

export default function Show({ lead, activities = [], can }) {
    const [tab, setTab] = useState('details');
    const [convertOpen, setConvertOpen] = useState(false);

    return (
        <AppLayout title={`${lead.first_name} ${lead.last_name}`}>
            <RecordDetailLayout
                backHref="/leads"
                backLabel="All leads"
                title={`${lead.first_name} ${lead.last_name}`}
                subtitle={lead.company ? `${lead.company} · Lead` : 'Lead'}
                badges={
                    <>
                        <Badge tone={lead.status_tone}>{lead.status_label}</Badge>
                        {lead.is_locked && <Badge tone="violet" dot={false}>Locked</Badge>}
                    </>
                }
                actions={
                    can.convert ? (
                        <Button onClick={() => setConvertOpen(true)}>
                            Convert lead
                            <ArrowRight className="h-4 w-4" />
                        </Button>
                    ) : null
                }
                highlights={[
                    { label: 'Source', value: lead.lead_source ?? '—' },
                    { label: 'Email', value: lead.email ?? '—' },
                    { label: 'Phone', value: lead.phone ?? '—' },
                    { label: 'Created', value: lead.created_at },
                ]}
                tabs={[
                    { key: 'details', label: 'Details' },
                    { key: 'notes', label: 'Notes' },
                ]}
                activeTab={tab}
                onTabChange={setTab}
                activityPanel={
                    <ActivityTimeline
                        title="Lead activity"
                        activities={activities}
                        relatableType="lead"
                        relatableId={lead.id}
                    />
                }
            >
                {lead.converted && (
                    <div className="mb-5 rounded-md border border-violet-200 bg-violet-50 px-4 py-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-violet-700">
                            Converted {lead.converted_at}
                        </p>
                        <div className="mt-2 flex flex-wrap gap-4 text-sm">
                            {lead.converted.account && (
                                <Link href={`/accounts/${lead.converted.account.id}`} className="inline-flex items-center gap-1.5 font-medium text-violet-800 hover:underline">
                                    <Building2 className="h-3.5 w-3.5" />
                                    {lead.converted.account.name}
                                </Link>
                            )}
                            {lead.converted.opportunity && (
                                <Link href={`/opportunities/${lead.converted.opportunity.id}`} className="inline-flex items-center gap-1.5 font-medium text-violet-800 hover:underline">
                                    <ArrowRight className="h-3.5 w-3.5" />
                                    {lead.converted.opportunity.name}
                                </Link>
                            )}
                        </div>
                    </div>
                )}

                {tab === 'details' && (
                    <div className="space-y-5">
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <DetailItem label="First name" value={lead.first_name} />
                            <DetailItem label="Last name" value={lead.last_name} />
                            <DetailItem label="Company" value={lead.company} />
                            <DetailItem label="Title" value={lead.title} />
                            <DetailItem
                                label="Email"
                                value={
                                    lead.email ? (
                                        <a href={`mailto:${lead.email}`} className="inline-flex items-center gap-1 text-brand-700 hover:underline">
                                            <Mail className="h-3.5 w-3.5" />
                                            {lead.email}
                                        </a>
                                    ) : null
                                }
                            />
                            <DetailItem
                                label="Phone"
                                value={
                                    lead.phone ? (
                                        <a href={`tel:${lead.phone}`} className="inline-flex items-center gap-1 text-brand-700 hover:underline">
                                            <Phone className="h-3.5 w-3.5" />
                                            {lead.phone}
                                        </a>
                                    ) : null
                                }
                            />
                        </dl>

                        {lead.is_locked && (
                            <p className="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                This lead has been converted and is locked for editing. Work continues on the
                                opportunity it created.
                            </p>
                        )}
                    </div>
                )}

                {tab === 'notes' && (
                    <div className="whitespace-pre-line text-sm leading-relaxed text-slate-700">
                        {lead.notes || 'No notes captured for this lead.'}
                    </div>
                )}
            </RecordDetailLayout>

            {convertOpen && <ConvertLeadModal lead={lead} open onClose={() => setConvertOpen(false)} />}
        </AppLayout>
    );
}
