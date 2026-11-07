import { cn } from '../../lib/format';

export default function Card({ className, children }) {
    return (
        <section className={cn('overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm', className)}>
            {children}
        </section>
    );
}

export function CardHeader({ title, description, action, className }) {
    return (
        <div className={cn('flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-3', className)}>
            <div>
                <h2 className="text-sm font-semibold text-slate-900">{title}</h2>
                {description && <p className="mt-0.5 text-xs text-slate-500">{description}</p>}
            </div>
            {action}
        </div>
    );
}

export function CardBody({ className, children }) {
    return <div className={cn('px-4 py-4', className)}>{children}</div>;
}
