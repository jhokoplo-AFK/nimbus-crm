import { Link } from '@inertiajs/react';
import { cn } from '../../lib/format';

export default function Pagination({ paginator }) {
    if (!paginator || !paginator.links || paginator.total === 0) return null;

    return (
        <div className="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs text-slate-500">
                Showing <span className="font-medium text-slate-700">{paginator.from ?? 0}</span>–
                <span className="font-medium text-slate-700">{paginator.to ?? 0}</span> of{' '}
                <span className="font-medium text-slate-700">{paginator.total}</span>
            </p>

            <nav className="flex flex-wrap items-center gap-1" aria-label="Pagination">
                {paginator.links.map((link, index) =>
                    link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            className={cn(
                                'min-w-8 rounded-md px-2.5 py-1.5 text-center text-xs font-medium transition',
                                link.active
                                    ? 'bg-slate-900 text-white'
                                    : 'text-slate-600 hover:bg-slate-100',
                            )}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <span
                            key={index}
                            className="min-w-8 rounded-md px-2.5 py-1.5 text-center text-xs text-slate-300"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ),
                )}
            </nav>
        </div>
    );
}
