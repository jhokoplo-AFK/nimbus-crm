import { Deferred } from '@inertiajs/react';

function SkeletonRows({ rows = 3 }) {
    return (
        <div className="space-y-2.5 p-4">
            {Array.from({ length: rows }).map((_, index) => (
                <div key={index} className="h-10 animate-pulse rounded-md bg-slate-100" />
            ))}
        </div>
    );
}

export default function DeferredSection({ data, rows = 3, children }) {
    return (
        <Deferred data={data} fallback={<SkeletonRows rows={rows} />}>
            {children}
        </Deferred>
    );
}
