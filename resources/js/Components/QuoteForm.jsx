import { useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Plus, ReceiptText, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { cn, formatMoney, formatMoneyPrecise } from '../lib/format';
import { calculateQuote, resolveApproval } from '../lib/quote';
import Badge from './UI/Badge';
import Button from './UI/Button';
import Card, { CardBody, CardHeader } from './UI/Card';
import { Field, Input, Select } from './UI/Input';

function newLine(product) {
    return {
        product_id: product?.product_id ?? '',
        quantity: 1,
        discount_pct: 0,
    };
}

export default function QuoteForm({
    opportunities = [],
    pricebooks = [],
    preselectedOpportunityId = null,
    defaults = { taxRate: 11, expirationDate: '' },
    settings,
    approvalPolicy = null,
    quotation = null,
    submitUrl,
    method = 'post',
}) {
    const { errors: pageErrors } = usePage().props;

    const initialPricebook =
        pricebooks.find((book) => book.id === quotation?.pricebook_id) ?? pricebooks[0];

    const initialLines =
        quotation?.lines?.map((line) => ({
            product_id: line.product_id,
            quantity: line.quantity,
            discount_pct: line.discount_pct,
        })) ?? (initialPricebook?.products?.[0] ? [newLine(initialPricebook.products[0])] : []);

    const { data, setData, post, put, processing, errors } = useForm({
        opportunity_id:
            quotation?.opportunity_id ?? preselectedOpportunityId ?? opportunities[0]?.id ?? '',
        pricebook_id: initialPricebook?.id ?? '',
        expiration_date: quotation?.expiration_date ?? defaults.expirationDate,
        discount_type: quotation?.discount_type ?? 'none',
        discount_value: quotation?.discount_value ?? 0,
        tax_rate: quotation?.tax_rate ?? defaults.taxRate,
        payment_term_days: quotation?.payment_term_days ?? defaults.paymentTermDays ?? 30,
        lines: initialLines,
    });

    const selectedBook = pricebooks.find((book) => Number(book.id) === Number(data.pricebook_id));
    const products = selectedBook?.products ?? [];

    const priceOf = (productId) =>
        Number(products.find((product) => Number(product.product_id) === Number(productId))?.list_price ?? 0);

    const totals = useMemo(
        () =>
            calculateQuote({
                lines: data.lines.map((line) => ({
                    ...line,
                    unit_price: priceOf(line.product_id),
                })),
                discountType: data.discount_type,
                discountValue: data.discount_value,
                taxRate: data.tax_rate,
            }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [data.lines, data.pricebook_id, data.discount_type, data.discount_value, data.tax_rate],
    );

    const approval = resolveApproval(totals, approvalPolicy, data.payment_term_days);
    const needsApproval = approval.requiresApproval;

    const changePricebook = (value) => {
        const book = pricebooks.find((candidate) => Number(candidate.id) === Number(value));
        const bookProducts = book?.products ?? [];
        const availableIds = new Set(bookProducts.map((product) => Number(product.product_id)));

        const kept = data.lines.filter((line) => availableIds.has(Number(line.product_id)));
        const lines = kept.length > 0 ? kept : bookProducts[0] ? [newLine(bookProducts[0])] : [];

        setData({ ...data, pricebook_id: value, lines });
    };

    const addLine = () => {
        const usedIds = new Set(data.lines.map((line) => Number(line.product_id)));
        const nextProduct = products.find((product) => !usedIds.has(Number(product.product_id))) ?? products[0];

        if (!nextProduct) return;

        setData({ ...data, lines: [...data.lines, newLine(nextProduct)] });
    };

    const removeLine = (index) => {
        const lines = data.lines.filter((_, position) => position !== index);

        setData({ ...data, lines: lines.length > 0 ? lines : [] });
    };

    const updateLine = (index, key, value) => {
        setData({
            ...data,
            lines: data.lines.map((line, position) =>
                position === index ? { ...line, [key]: value } : line,
            ),
        });
    };

    const submit = (event) => {
        event.preventDefault();

        const options = { preserveScroll: true };

        if (method === 'put') {
            put(submitUrl, options);
        } else {
            post(submitUrl, options);
        }
    };

    const fieldError = (key) => errors[key];
    const lineError = (index, key) => errors[`lines.${index}.${key}`];

    return (
        <form onSubmit={submit} className="grid gap-4 lg:grid-cols-3">
            <div className="space-y-4 lg:col-span-2">
                <Card>
                    <CardHeader
                        title="Quotation details"
                        description="Prices are always resolved from the selected pricebook."
                    />
                    <CardBody className="grid gap-4 sm:grid-cols-2">
                        <Field label="Opportunity" htmlFor="opportunity_id" required error={fieldError('opportunity_id')}>
                            <Select
                                id="opportunity_id"
                                value={data.opportunity_id}
                                onChange={(event) => setData('opportunity_id', event.target.value)}
                                invalid={Boolean(fieldError('opportunity_id'))}
                            >
                                <option value="">Select an opportunity…</option>
                                {opportunities.map((opportunity) => (
                                    <option key={opportunity.id} value={opportunity.id}>
                                        {opportunity.name} — {opportunity.account} ({formatMoney(opportunity.amount)})
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field label="Pricebook" htmlFor="pricebook_id" required error={fieldError('pricebook_id')}>
                            <Select
                                id="pricebook_id"
                                value={data.pricebook_id}
                                onChange={(event) => changePricebook(event.target.value)}
                                invalid={Boolean(fieldError('pricebook_id'))}
                            >
                                {pricebooks.map((book) => (
                                    <option key={book.id} value={book.id}>
                                        {book.name}
                                        {book.is_standard ? ' (Standard)' : ''}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field label="Expiration date" htmlFor="expiration_date" error={fieldError('expiration_date')}>
                            <Input
                                id="expiration_date"
                                type="date"
                                value={data.expiration_date ?? ''}
                                onChange={(event) => setData('expiration_date', event.target.value)}
                            />
                        </Field>

                        <Field
                            label="Tax rate (%)"
                            htmlFor="tax_rate"
                            error={fieldError('tax_rate')}
                            hint="PPN default: 11%"
                        >
                            <Input
                                id="tax_rate"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                value={data.tax_rate}
                                onChange={(event) => setData('tax_rate', event.target.value)}
                            />
                        </Field>

                        <Field
                            label="Payment term (days)"
                            htmlFor="payment_term_days"
                            error={fieldError('payment_term_days')}
                            hint="0 = cash, 30 = Net 30, 60 = Net 60"
                        >
                            <Input
                                id="payment_term_days"
                                type="number"
                                min="0"
                                max="365"
                                step="1"
                                value={data.payment_term_days}
                                onChange={(event) => setData('payment_term_days', event.target.value)}
                            />
                        </Field>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader
                        title="Line items"
                        description={`${data.lines.length} product${data.lines.length === 1 ? '' : 's'} on this quotation`}
                        action={
                            <Button type="button" size="sm" variant="secondary" onClick={addLine} disabled={products.length === 0}>
                                <Plus className="h-3.5 w-3.5" />
                                Add product
                            </Button>
                        }
                    />

                    <div className="divide-y divide-slate-100">
                        {data.lines.length === 0 && (
                            <p className="px-4 py-8 text-center text-sm text-slate-500">
                                Add a product to start building this quotation.
                            </p>
                        )}

                        {data.lines.map((line, index) => {
                            const product = products.find(
                                (candidate) => Number(candidate.product_id) === Number(line.product_id),
                            );
                            const row = totals.lines[index];

                            return (
                                <div key={index} className="grid gap-3 px-4 py-3 sm:grid-cols-12 sm:items-end">
                                    <div className="sm:col-span-5">
                                        <Field label={index === 0 ? 'Product' : undefined} error={lineError(index, 'product_id')}>
                                            <Select
                                                value={line.product_id}
                                                onChange={(event) => updateLine(index, 'product_id', event.target.value)}
                                            >
                                                {products.map((candidate) => (
                                                    <option key={candidate.product_id} value={candidate.product_id}>
                                                        {candidate.sku} — {candidate.name}
                                                    </option>
                                                ))}
                                            </Select>
                                        </Field>
                                    </div>

                                    <div className="sm:col-span-2">
                                        <Field label={index === 0 ? 'Qty' : undefined} error={lineError(index, 'quantity')}>
                                            <Input
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                value={line.quantity}
                                                onChange={(event) => updateLine(index, 'quantity', event.target.value)}
                                            />
                                        </Field>
                                    </div>

                                    <div className="sm:col-span-2">
                                        <Field label={index === 0 ? 'Unit price' : undefined}>
                                            <div className="flex h-9 items-center rounded-md bg-slate-50 px-2.5 text-sm text-slate-600 ring-1 ring-inset ring-slate-200">
                                                {formatMoneyPrecise(product?.list_price ?? 0)}
                                            </div>
                                        </Field>
                                    </div>

                                    <div className="sm:col-span-1">
                                        <Field label={index === 0 ? 'Disc %' : undefined} error={lineError(index, 'discount_pct')}>
                                            <Input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                value={line.discount_pct}
                                                onChange={(event) => updateLine(index, 'discount_pct', event.target.value)}
                                            />
                                        </Field>
                                    </div>

                                    <div className="flex items-center justify-between gap-2 sm:col-span-2 sm:justify-end">
                                        <div className="text-right">
                                            {row?.lineDiscount > 0 && (
                                                <p className="text-[11px] font-medium text-rose-600">
                                                    −{formatMoneyPrecise(row.lineDiscount)} line discount
                                                </p>
                                            )}
                                            <span className="text-sm font-semibold text-slate-900">
                                                {formatMoneyPrecise(row?.total ?? 0)}
                                            </span>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => removeLine(index)}
                                            className="rounded-md p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                            aria-label="Remove line"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {fieldError('lines') && (
                        <p className="px-4 pb-3 text-xs font-medium text-rose-600">{fieldError('lines')}</p>
                    )}
                </Card>
            </div>

            <div className="space-y-4">
                <Card className="lg:sticky lg:top-20">
                    <CardHeader title="Summary" />
                    <CardBody className="space-y-4">
                        <div className="space-y-2 text-sm">
                            <div className="flex items-center justify-between text-slate-600">
                                <span>Subtotal</span>
                                <span className="font-medium text-slate-900">{formatMoneyPrecise(totals.subtotal)}</span>
                            </div>

                            {totals.lineDiscountAmount > 0 && (
                                <div className="flex items-center justify-between text-slate-600">
                                    <span>Line discounts</span>
                                    <span className="font-medium text-rose-600">
                                        −{formatMoneyPrecise(totals.lineDiscountAmount)}
                                    </span>
                                </div>
                            )}

                            <div className="space-y-1.5 rounded-md bg-slate-50 p-2.5">
                                <div className="flex items-center gap-2">
                                    <Select
                                        value={data.discount_type}
                                        onChange={(event) => setData('discount_type', event.target.value)}
                                        className="h-8 flex-1 text-xs"
                                    >
                                        <option value="none">No discount</option>
                                        <option value="percent">Discount %</option>
                                        <option value="flat">Discount amount</option>
                                    </Select>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        disabled={data.discount_type === 'none'}
                                        value={data.discount_value}
                                        onChange={(event) => setData('discount_value', event.target.value)}
                                        className="h-8 w-24 text-xs"
                                    />
                                </div>
                                <div className="flex items-center justify-between text-xs text-slate-500">
                                    <span>Quote discount</span>
                                    <span className="font-medium text-slate-700">
                                        −{formatMoneyPrecise(totals.discountAmount)}
                                    </span>
                                </div>
                                {fieldError('discount_value') && (
                                    <p className="text-[11px] text-rose-600">{fieldError('discount_value')}</p>
                                )}
                            </div>

                            {totals.effectiveDiscountPct > 0 && (
                                <p className="text-[11px] text-slate-400">
                                    Combined discount {totals.effectiveDiscountPct}% of{' '}
                                    {formatMoneyPrecise(totals.grossSubtotal)} gross
                                </p>
                            )}

                            <div className="flex items-center justify-between text-slate-600">
                                <span>Tax ({totals.taxRate}%)</span>
                                <span className="font-medium text-slate-900">{formatMoneyPrecise(totals.taxAmount)}</span>
                            </div>

                            <div className="flex items-center justify-between border-t border-slate-200 pt-2 text-base font-semibold text-slate-900">
                                <span>Grand total</span>
                                <span>{formatMoneyPrecise(totals.grandTotal)}</span>
                            </div>
                        </div>

                        <div
                            className={cn(
                                'flex items-start gap-2 rounded-md px-3 py-2 text-xs',
                                needsApproval ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-700',
                            )}
                        >
                            {needsApproval ? (
                                <>
                                    <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                    <span>
                                        Outside your pricing authority — this quote needs{' '}
                                        {approval.requiredLabel ?? 'a manager'} approval
                                        {approval.requiredLevel ? ` (level ${approval.requiredLevel})` : ''}.
                                        {approval.myDiscountLimit !== null && approval.myAmountLimit !== null && (
                                            <>
                                                {' '}
                                                Your limit: {approval.myDiscountLimit}% discount and{' '}
                                                {formatMoney(approval.myAmountLimit)}.
                                            </>
                                        )}
                                        {approval.myPaymentTermLimit !== null && (
                                            <> Your payment term limit: Net {approval.myPaymentTermLimit}.</>
                                        )}
                                    </span>
                                </>
                            ) : (
                                <>
                                    <ReceiptText className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                    <span>Within policy — this quotation will stay in Draft.</span>
                                </>
                            )}
                        </div>

                        {pageErrors?.quotation && (
                            <p className="rounded-md bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">
                                {pageErrors.quotation}
                            </p>
                        )}

                        <div className="flex items-center gap-2">
                            <Badge tone={needsApproval ? 'amber' : 'emerald'} dot={false}>
                                {needsApproval ? 'In Review' : 'Draft'}
                            </Badge>
                            <span className="text-[11px] text-slate-400">status on save</span>
                        </div>

                        <Button type="submit" className="w-full" loading={processing}>
                            {quotation ? 'Save quotation' : 'Create quotation'}
                        </Button>
                    </CardBody>
                </Card>
            </div>
        </form>
    );
}
