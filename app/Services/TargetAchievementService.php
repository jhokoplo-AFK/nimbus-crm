<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the target vs achievement model for the dashboard.
 *
 * Achievement is net booked revenue (`sales_orders.subtotal`) recognised on the
 * order date. Period, sales-rep and customer views use the order header;
 * brand and product views use the order line items, whose totals sum to the
 * same net figure. Values are aggregated in PHP from one year of orders so the
 * queries stay portable across SQLite, MySQL and PostgreSQL.
 */
class TargetAchievementService
{
    public function years(): array
    {
        $fromTargets = SalesTarget::query()->distinct()->pluck('year');
        $fromOrders = SalesOrder::query()->pluck('order_date')
            ->filter()
            ->map(fn ($date): int => (int) Carbon::parse($date)->format('Y'));

        return $fromTargets
            ->merge($fromOrders)
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function forYear(int $year): array
    {
        $targets = SalesTarget::query()->forYear($year)->get();

        $orders = SalesOrder::query()
            ->whereYear('order_date', $year)
            ->get(['id', 'subtotal', 'account_id', 'user_id', 'order_date']);

        $items = SalesOrderItem::query()
            ->whereIn('sales_order_id', $orders->pluck('id'))
            ->with(['product:id,name,brand_id', 'product.brand:id,name'])
            ->get(['id', 'sales_order_id', 'product_id', 'total_price']);

        $companyTargets = $targets->filter(fn (SalesTarget $target): bool => $target->isCompanyWide());

        $annualTarget = (float) $companyTargets->filter(fn (SalesTarget $t): bool => $t->isAnnual())->sum('target_amount');
        $quarterTargets = $companyTargets->filter(fn (SalesTarget $t): bool => $t->quarter !== null && $t->month === null);
        $monthTargets = $companyTargets->filter(fn (SalesTarget $t): bool => $t->month !== null);

        if ($annualTarget <= 0) {
            $annualTarget = (float) ($monthTargets->sum('target_amount') ?: $quarterTargets->sum('target_amount'));
        }

        $achievedByMonth = $orders
            ->groupBy(fn (SalesOrder $order): int => (int) $order->order_date->format('n'))
            ->map(fn (Collection $rows): float => (float) $rows->sum('subtotal'));

        $monthly = collect(range(1, 12))->map(function (int $month) use ($monthTargets, $quarterTargets, $annualTarget, $achievedByMonth): array {
            $quarter = intdiv($month - 1, 3) + 1;

            $target = (float) $monthTargets->where('month', $month)->sum('target_amount');

            if ($target <= 0) {
                $quarterly = (float) $quarterTargets->where('quarter', $quarter)->sum('target_amount');
                $target = $quarterly > 0 ? $quarterly / 3 : $annualTarget / 12;
            }

            return $this->metric(
                Carbon::create()->month($month)->format('M'),
                round($target, 2),
                round((float) ($achievedByMonth[$month] ?? 0), 2),
                ['month' => $month, 'quarter' => $quarter],
            );
        })->values()->all();

        $quarterly = collect(range(1, 4))->map(function (int $quarter) use ($quarterTargets, $monthTargets, $annualTarget, $monthly): array {
            $target = (float) $quarterTargets->where('quarter', $quarter)->sum('target_amount');

            if ($target <= 0) {
                $months = range(($quarter - 1) * 3 + 1, $quarter * 3);
                $target = (float) $monthTargets->whereIn('month', $months)->sum('target_amount');
            }

            if ($target <= 0) {
                $target = $annualTarget / 4;
            }

            $achieved = (float) collect($monthly)
                ->where('quarter', $quarter)
                ->sum('achieved');

            return $this->metric("Q{$quarter}", round($target, 2), round($achieved, 2), ['quarter' => $quarter]);
        })->values()->all();

        $annualAchieved = round((float) $orders->sum('subtotal'), 2);

        return [
            'year' => $year,
            'summary' => $this->metric('Annual', round($annualTarget, 2), $annualAchieved, [
                'orders' => $orders->count(),
                'average_deal' => $orders->count() > 0 ? round($annualAchieved / $orders->count(), 2) : 0.0,
            ]),
            'annual' => $this->metric((string) $year, round($annualTarget, 2), $annualAchieved),
            'monthly' => $monthly,
            'quarterly' => $quarterly,
            'by_sales' => $this->bySales($targets, $orders),
            'by_brand' => $this->byBrand($targets, $items),
            'by_customer' => $this->byCustomer($targets, $orders),
            'by_product' => $this->byProduct($targets, $items),
        ];
    }

    /**
     * @param  Collection<int, SalesTarget>  $targets
     * @param  Collection<int, SalesOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function bySales(Collection $targets, Collection $orders): array
    {
        $rows = $targets->filter(fn (SalesTarget $target): bool => $target->user_id !== null
            && $target->brand_id === null
            && $target->account_id === null
            && $target->product_id === null);

        $userIds = $rows->pluck('user_id')
            ->merge($orders->pluck('user_id')->filter())
            ->unique()
            ->values();

        $users = User::query()->whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');

        return $userIds->map(function (int $userId) use ($rows, $orders, $users): array {
            $userRows = $rows->where('user_id', $userId);
            $annual = (float) $userRows->filter(fn (SalesTarget $t): bool => $t->isAnnual())->sum('target_amount');
            $target = $annual > 0 ? $annual : (float) $userRows->sum('target_amount');

            $achieved = (float) $orders
                ->where('user_id', $userId)
                ->sum('subtotal');

            $user = $users->get($userId);

            return $this->metric(
                $user?->name ?? 'Unassigned',
                round($target, 2),
                round($achieved, 2),
                ['user_id' => $userId],
            );
        })->sortByDesc('achieved')->values()->all();
    }

    /**
     * @param  Collection<int, SalesTarget>  $targets
     * @param  Collection<int, SalesOrderItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function byBrand(Collection $targets, Collection $items): array
    {
        $rows = $targets->filter(fn (SalesTarget $target): bool => $target->brand_id !== null);

        $achieved = $items->groupBy(fn (SalesOrderItem $item): string => (string) ($item->product?->brand_id ?? 'unassigned'))
            ->map(fn (Collection $group): float => (float) $group->sum('total_price'));

        $brandIds = $rows->pluck('brand_id')->merge($achieved->keys()->filter(fn ($key): bool => $key !== 'unassigned'))->unique();
        $brands = Brand::query()->whereIn('id', $brandIds)->get(['id', 'name'])->keyBy('id');

        $result = $brands->map(function (Brand $brand) use ($rows, $achieved): array {
            return $this->metric(
                $brand->name,
                round((float) $rows->where('brand_id', $brand->id)->sum('target_amount'), 2),
                round((float) ($achieved[$brand->id] ?? 0), 2),
                ['brand_id' => $brand->id],
            );
        })->values()->all();

        if (isset($achieved['unassigned'])) {
            $result[] = $this->metric('Unbranded', 0.0, round((float) $achieved['unassigned'], 2), ['brand_id' => null]);
        }

        return collect($result)->sortByDesc('achieved')->values()->all();
    }

    /**
     * @param  Collection<int, SalesTarget>  $targets
     * @param  Collection<int, SalesOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function byCustomer(Collection $targets, Collection $orders): array
    {
        $rows = $targets->filter(fn (SalesTarget $target): bool => $target->account_id !== null);

        $achieved = $orders->groupBy('account_id')->map(fn (Collection $group): float => (float) $group->sum('subtotal'));

        $accountIds = $rows->pluck('account_id')->merge($achieved->keys())->unique();
        $accounts = Account::query()->whereIn('id', $accountIds)->get(['id', 'name'])->keyBy('id');

        return $accounts->map(function (Account $account) use ($rows, $achieved): array {
            return $this->metric(
                $account->name,
                round((float) $rows->where('account_id', $account->id)->sum('target_amount'), 2),
                round((float) ($achieved[$account->id] ?? 0), 2),
                ['account_id' => $account->id],
            );
        })->sortByDesc('achieved')->values()->all();
    }

    /**
     * @param  Collection<int, SalesTarget>  $targets
     * @param  Collection<int, SalesOrderItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function byProduct(Collection $targets, Collection $items): array
    {
        $rows = $targets->filter(fn (SalesTarget $target): bool => $target->product_id !== null);

        $achieved = $items->whereNotNull('product_id')
            ->groupBy('product_id')
            ->map(fn (Collection $group): float => (float) $group->sum('total_price'));

        $productIds = $rows->pluck('product_id')->merge($achieved->keys())->unique();
        $products = Product::query()->whereIn('id', $productIds)->get(['id', 'name'])->keyBy('id');

        return $products->map(function (Product $product) use ($rows, $achieved): array {
            return $this->metric(
                $product->name,
                round((float) $rows->where('product_id', $product->id)->sum('target_amount'), 2),
                round((float) ($achieved[$product->id] ?? 0), 2),
                ['product_id' => $product->id],
            );
        })->sortByDesc('achieved')->values()->all();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function metric(string $label, float $target, float $achieved, array $extra = []): array
    {
        return [
            'label' => $label,
            'target' => $target,
            'achieved' => $achieved,
            'gap' => round($achieved - $target, 2),
            'attainment' => $target > 0 ? round(($achieved / $target) * 100, 1) : ($achieved > 0 ? 100.0 : 0.0),
            ...$extra,
        ];
    }
}
