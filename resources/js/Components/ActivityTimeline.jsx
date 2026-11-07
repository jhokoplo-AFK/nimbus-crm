import { router, useForm, usePage } from '@inertiajs/react';
import { CheckSquare, Mail, Phone, Users } from 'lucide-react';
import { cn, formatDate } from '../lib/format';
import Badge from './UI/Badge';
import Button from './UI/Button';
import { Field, Input, Select } from './UI/Input';

const TYPE_ICONS = {
    Call: Phone,
    Email: Mail,
    Meeting: Users,
    Task: CheckSquare,
};

const TYPE_TONES = {
    Call: 'bg-sky-50 text-sky-700',
    Email: 'bg-violet-50 text-violet-700',
    Meeting: 'bg-indigo-50 text-indigo-700',
    Task: 'bg-amber-50 text-amber-700',
};

export default function ActivityTimeline({
    activities = [],
    relatableType,
    relatableId,
    canLog = true,
    title = 'Activity',
}) {
    const { enums } = usePage().props;

    const { data, setData, post, processing, reset, errors } = useForm({
        subject: '',
        type: 'Call',
        due_date: '',
        relatable_type: relatableType,
        relatable_id: relatableId,
    });

    const submit = (event) => {
        event.preventDefault();

        post('/activities', {
            preserveScroll: true,
            onSuccess: () => reset('subject'),
        });
    };

    return (
        <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h2 className="text-sm font-semibold text-slate-900">{title}</h2>
                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                    {activities.length}
                </span>
            </div>

            {canLog && (
                <form onSubmit={submit} className="space-y-2 border-b border-slate-200 bg-slate-50/70 px-4 py-3">
                    <Input
                        value={data.subject}
                        onChange={(event) => setData('subject', event.target.value)}
                        placeholder="Log a call, email or task…"
                        invalid={Boolean(errors.subject)}
                    />
                    <div className="flex gap-2">
                        <Select value={data.type} onChange={(event) => setData('type', event.target.value)} className="w-28">
                            {(enums?.activityTypes ?? []).map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                        <Input
                            type="date"
                            value={data.due_date}
                            onChange={(event) => setData('due_date', event.target.value)}
                            className="w-36"
                        />
                        <Button type="submit" size="md" loading={processing} disabled={!data.subject.trim()}>
                            Log
                        </Button>
                    </div>
                    {errors.subject && <p className="text-xs text-rose-600">{errors.subject}</p>}
                </form>
            )}

            <ol className="divide-y divide-slate-100">
                {activities.length === 0 && (
                    <li className="px-4 py-8 text-center text-sm text-slate-500">No activity logged yet.</li>
                )}

                {activities.map((activity) => {
                    const Icon = TYPE_ICONS[activity.type] ?? CheckSquare;
                    const overdue =
                        activity.status === 'Open' &&
                        activity.due_date &&
                        new Date(activity.due_date) < new Date(new Date().toDateString());

                    return (
                        <li key={activity.id} className="flex gap-3 px-4 py-3">
                            <span
                                className={cn(
                                    'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full',
                                    TYPE_TONES[activity.type] ?? 'bg-slate-100 text-slate-600',
                                )}
                            >
                                <Icon className="h-3.5 w-3.5" />
                            </span>

                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium text-slate-900">{activity.subject}</p>
                                <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-500">
                                    <span>{activity.type}</span>
                                    {activity.due_date && (
                                        <span className={cn(overdue && 'font-semibold text-rose-600')}>
                                            due {formatDate(activity.due_date)}
                                        </span>
                                    )}
                                    {activity.user && <span>· {activity.user}</span>}
                                    {activity.created_at && <span>· {activity.created_at}</span>}
                                </div>

                                {activity.status === 'Open' && (
                                    <div className="mt-1.5 flex items-center gap-2">
                                        <Badge tone="amber" dot={false}>
                                            Open
                                        </Badge>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.patch(`/activities/${activity.id}/complete`, {}, { preserveScroll: true })
                                            }
                                            className="text-[11px] font-medium text-brand-600 hover:text-brand-700"
                                        >
                                            Mark complete
                                        </button>
                                    </div>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
