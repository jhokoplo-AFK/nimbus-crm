import { Inbox } from 'lucide-react';

export default function EmptyState({ icon: Icon = Inbox, title, description, action }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
            <span className="rounded-full bg-slate-100 p-3 text-slate-500">
                <Icon className="h-5 w-5" />
            </span>
            <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
            {description && <p className="max-w-sm text-sm text-slate-500">{description}</p>}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
