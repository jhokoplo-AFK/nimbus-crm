import { Loader2 } from 'lucide-react';
import { cn } from '../../lib/format';

const VARIANTS = {
    primary:
        'bg-brand-600 text-white shadow-sm hover:bg-brand-700 focus-visible:outline-brand-600',
    secondary:
        'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 shadow-sm hover:bg-slate-50 focus-visible:outline-slate-400',
    subtle: 'bg-slate-900 text-white hover:bg-slate-800 focus-visible:outline-slate-900',
    ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
    danger: 'bg-rose-600 text-white hover:bg-rose-500 focus-visible:outline-rose-600',
    'danger-ghost': 'text-rose-600 hover:bg-rose-50',
};

const SIZES = {
    sm: 'h-8 gap-1.5 px-3 text-xs',
    md: 'h-9 gap-2 px-3.5 text-sm',
    lg: 'h-10 gap-2 px-4 text-sm',
};

export default function Button({
    variant = 'primary',
    size = 'md',
    className,
    loading = false,
    disabled = false,
    children,
    ...props
}) {
    return (
        <button
            className={cn(
                'inline-flex items-center justify-center rounded-md font-medium transition focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60',
                VARIANTS[variant] ?? VARIANTS.primary,
                SIZES[size] ?? SIZES.md,
                className,
            )}
            disabled={disabled || loading}
            {...props}
        >
            {loading && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
            {children}
        </button>
    );
}
