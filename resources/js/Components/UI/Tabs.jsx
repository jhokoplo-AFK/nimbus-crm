import { cn } from '../../lib/format';

export default function Tabs({ tabs, active, onChange }) {
    return (
        <div className="flex flex-wrap gap-1 border-b border-slate-200 px-2 sm:px-4">
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    type="button"
                    onClick={() => onChange(tab.key)}
                    className={cn(
                        '-mb-px flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition',
                        active === tab.key
                            ? 'border-brand-600 text-brand-700'
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700',
                    )}
                >
                    {tab.label}
                    {tab.count !== undefined && tab.count !== null && (
                        <span className="rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600">
                            {tab.count}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}
