import { cn } from '../../lib/format';

const CONTROL =
    'block w-full rounded-md border-0 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500';

export function Field({ label, htmlFor, error, hint, required, className, children }) {
    return (
        <div className={cn('space-y-1.5', className)}>
            {label && (
                <label htmlFor={htmlFor} className="block text-xs font-medium text-slate-600">
                    {label}
                    {required && <span className="ml-0.5 text-rose-500">*</span>}
                </label>
            )}
            {children}
            {error ? (
                <p className="text-xs font-medium text-rose-600">{error}</p>
            ) : (
                hint && <p className="text-xs text-slate-500">{hint}</p>
            )}
        </div>
    );
}

export function Input({ className, invalid, ...props }) {
    return <input className={cn(CONTROL, invalid && 'ring-rose-400 focus:ring-rose-500', className)} {...props} />;
}

export function Select({ className, invalid, children, ...props }) {
    return (
        <select className={cn(CONTROL, 'pr-8', invalid && 'ring-rose-400 focus:ring-rose-500', className)} {...props}>
            {children}
        </select>
    );
}

export function Textarea({ className, invalid, rows = 3, ...props }) {
    return (
        <textarea
            rows={rows}
            className={cn(CONTROL, 'resize-y', invalid && 'ring-rose-400 focus:ring-rose-500', className)}
            {...props}
        />
    );
}

export function Checkbox({ className, label, ...props }) {
    return (
        <label className="flex items-center gap-2 text-sm text-slate-700">
            <input
                type="checkbox"
                className={cn('h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-600', className)}
                {...props}
            />
            {label}
        </label>
    );
}
