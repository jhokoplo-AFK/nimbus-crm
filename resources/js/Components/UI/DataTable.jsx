import { cn } from '../../lib/format';

export default function DataTable({
    columns,
    rows,
    keyField = 'id',
    emptyMessage = 'No records found.',
    onRowClick,
}) {
    return (
        <div className="scrollbar-thin overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
                <thead className="bg-slate-50">
                    <tr>
                        {columns.map((column) => (
                            <th
                                key={column.key}
                                scope="col"
                                className={cn(
                                    'whitespace-nowrap px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500',
                                    column.align === 'right' && 'text-right',
                                    column.className,
                                )}
                            >
                                {column.header}
                            </th>
                        ))}
                    </tr>
                </thead>

                <tbody className="divide-y divide-slate-100 bg-white">
                    {rows.length === 0 ? (
                        <tr>
                            <td colSpan={columns.length} className="px-4 py-12 text-center text-sm text-slate-500">
                                {emptyMessage}
                            </td>
                        </tr>
                    ) : (
                        rows.map((row) => (
                            <tr
                                key={row[keyField]}
                                onClick={onRowClick ? () => onRowClick(row) : undefined}
                                className={cn('transition hover:bg-slate-50/80', onRowClick && 'cursor-pointer')}
                            >
                                {columns.map((column) => (
                                    <td
                                        key={column.key}
                                        className={cn(
                                            'px-4 py-3 align-middle text-slate-700',
                                            column.align === 'right' && 'text-right',
                                            column.className,
                                        )}
                                    >
                                        {column.render ? column.render(row) : row[column.key]}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}
