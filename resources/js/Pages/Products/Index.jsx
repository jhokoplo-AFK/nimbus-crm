import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Badge from '../../Components/UI/Badge';
import Button from '../../Components/UI/Button';
import Card, { CardBody, CardHeader } from '../../Components/UI/Card';
import DataTable from '../../Components/UI/DataTable';
import { Input } from '../../Components/UI/Input';
import Pagination from '../../Components/UI/Pagination';
import { formatMoneyPrecise } from '../../lib/format';

export default function Index({ products, filters, pricebooks = [] }) {
    const [search, setSearch] = useState(filters.search ?? '');

    const columns = [
        {
            key: 'sku',
            header: 'SKU',
            render: (product) => <span className="font-mono text-xs text-slate-600">{product.sku}</span>,
        },
        {
            key: 'name',
            header: 'Product',
            render: (product) => (
                <div className="min-w-0">
                    <p className="font-medium text-slate-900">{product.name}</p>
                    <p className="truncate text-xs text-slate-500">{product.description || '—'}</p>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (product) => (
                <Badge tone={product.is_active ? 'emerald' : 'slate'} dot={false}>
                    {product.is_active ? 'Active' : 'Inactive'}
                </Badge>
            ),
        },
        {
            key: 'prices',
            header: 'Pricebook pricing',
            render: (product) => (
                <div className="flex flex-wrap gap-1.5">
                    {product.prices.length === 0 && <span className="text-xs text-slate-400">Not priced</span>}
                    {product.prices.map((price) => (
                        <span
                            key={`${product.id}-${price.pricebook}`}
                            className={`rounded px-1.5 py-0.5 text-[11px] ${
                                price.is_active ? 'bg-slate-100 text-slate-700' : 'bg-slate-50 text-slate-400 line-through'
                            }`}
                            title={price.is_active ? 'Active pricebook' : 'Inactive pricebook'}
                        >
                            {price.pricebook}: {formatMoneyPrecise(price.list_price)}
                        </span>
                    ))}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Products & pricebooks">
            <div className="space-y-4">
                <header>
                    <h1 className="text-xl font-semibold tracking-tight text-slate-900">Products & pricebooks</h1>
                    <p className="text-sm text-slate-500">
                        The quotation engine only accepts prices that exist in an active pricebook.
                    </p>
                </header>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {pricebooks.map((pricebook) => (
                        <Card key={pricebook.id}>
                            <CardBody className="space-y-2">
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">{pricebook.name}</p>
                                        <p className="text-[11px] text-slate-500">
                                            {pricebook.entries_count} priced product{pricebook.entries_count === 1 ? '' : 's'}
                                        </p>
                                    </div>
                                    <div className="flex flex-col items-end gap-1">
                                        <Badge tone={pricebook.is_active ? 'emerald' : 'slate'} dot={false}>
                                            {pricebook.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                        {pricebook.is_standard && <Badge tone="brand" dot={false}>Standard</Badge>}
                                    </div>
                                </div>
                            </CardBody>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader title="Product catalog" description="List prices across every pricebook." />
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get('/products', { search }, { preserveState: true, replace: true });
                        }}
                        className="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-3"
                    >
                        <div className="relative min-w-[220px] flex-1">
                            <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search SKU or product name…"
                                className="pl-8"
                            />
                        </div>
                        <Button type="submit" variant="secondary">Search</Button>
                    </form>

                    <DataTable columns={columns} rows={products.data} emptyMessage="No products found." />
                    <Pagination paginator={products} />
                </Card>
            </div>
        </AppLayout>
    );
}
