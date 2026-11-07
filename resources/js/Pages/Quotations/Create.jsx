import { Link, usePage } from '@inertiajs/react';
import QuoteForm from '../../Components/QuoteForm';
import AppLayout from '../../Layouts/AppLayout';

export default function Create({
    pricebooks = [],
    opportunities = [],
    preselectedOpportunityId,
    defaults,
    approvalPolicy = null,
}) {
    const { settings } = usePage().props;

    return (
        <AppLayout title="New quotation">
            <div className="space-y-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-slate-900">New quotation</h1>
                        <p className="text-sm text-slate-500">
                            Build a quote from an active pricebook. Totals and approval routing are recalculated on save.
                        </p>
                    </div>
                    <Link href="/quotations" className="text-xs font-medium text-slate-500 hover:text-slate-800">
                        ← Back to quotations
                    </Link>
                </header>

                {pricebooks.length === 0 ? (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        No active pricebook is available. Activate a pricebook before creating quotations.
                    </div>
                ) : (
                    <QuoteForm
                        opportunities={opportunities}
                        pricebooks={pricebooks}
                        preselectedOpportunityId={preselectedOpportunityId}
                        defaults={defaults}
                        settings={settings}
                        approvalPolicy={approvalPolicy}
                        submitUrl="/quotations"
                        method="post"
                    />
                )}
            </div>
        </AppLayout>
    );
}
