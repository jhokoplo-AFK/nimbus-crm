import { cn } from '../../lib/format';

const TONES = {
    slate: 'bg-slate-100 text-slate-700 ring-slate-200',
    sky: 'bg-sky-50 text-sky-700 ring-sky-200',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    rose: 'bg-rose-50 text-rose-700 ring-rose-200',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200',
    violet: 'bg-violet-50 text-violet-700 ring-violet-200',
    indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
};

const DOTS = {
    slate: 'bg-slate-400',
    sky: 'bg-sky-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    violet: 'bg-violet-500',
    indigo: 'bg-indigo-500',
};

export default function Badge({ tone = 'slate', dot = true, className, children }) {
    const normalized = String(tone ?? 'slate').toLowerCase();

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                TONES[normalized] ?? TONES.slate,
                className,
            )}
        >
            {dot && <span className={cn('h-1.5 w-1.5 rounded-full', DOTS[normalized] ?? DOTS.slate)} />}
            {children}
        </span>
    );
}
