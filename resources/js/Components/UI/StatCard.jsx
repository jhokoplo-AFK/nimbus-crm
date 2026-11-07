import { cn } from '../../lib/format';

const TONES = {
    brand: 'bg-brand-50 text-brand-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    amber: 'bg-amber-50 text-amber-700',
    rose: 'bg-rose-50 text-rose-700',
    slate: 'bg-slate-100 text-slate-700',
    violet: 'bg-violet-50 text-violet-700',
};

export default function StatCard({ label, value, icon: Icon, hint, tone = 'brand' }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
                    <p className="mt-2 truncate text-2xl font-semibold tracking-tight text-slate-900">{value}</p>
                    {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
                </div>
                {Icon && (
                    <span className={cn('rounded-md p-2', TONES[tone] ?? TONES.brand)}>
                        <Icon className="h-4 w-4" />
                    </span>
                )}
            </div>
        </div>
    );
}
