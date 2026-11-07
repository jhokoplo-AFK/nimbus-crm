<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\TargetAchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetAchievementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_period_sales_and_dimension_attainment(): void
    {
        $year = now()->year;

        $brand = Brand::factory()->create(['name' => 'Acme Cloud']);
        $product = Product::factory()->create(['brand_id' => $brand->id, 'name' => 'Acme Suite']);
        $account = Account::factory()->create(['name' => 'Globex']);
        $rep = User::factory()->create(['name' => 'Dana Rep']);

        $order = SalesOrder::factory()->create([
            'account_id' => $account->id,
            'user_id' => $rep->id,
            'order_date' => "{$year}-02-14",
            'subtotal' => 1000,
            'total_amount' => 1110,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);

        SalesTarget::create(['year' => $year, 'target_amount' => 12000]);
        SalesTarget::create(['year' => $year, 'quarter' => 1, 'target_amount' => 3000]);
        SalesTarget::create(['year' => $year, 'month' => 2, 'target_amount' => 1000]);
        SalesTarget::create(['year' => $year, 'user_id' => $rep->id, 'target_amount' => 6000]);
        SalesTarget::create(['year' => $year, 'brand_id' => $brand->id, 'target_amount' => 5000]);
        SalesTarget::create(['year' => $year, 'account_id' => $account->id, 'target_amount' => 4000]);
        SalesTarget::create(['year' => $year, 'product_id' => $product->id, 'target_amount' => 3000]);

        $report = app(TargetAchievementService::class)->forYear($year);

        $this->assertSame($year, $report['year']);
        $this->assertSame(12000.0, (float) $report['summary']['target']);
        $this->assertSame(1000.0, (float) $report['summary']['achieved']);
        $this->assertSame(1, $report['summary']['orders']);

        $this->assertSame(3000.0, (float) $report['quarterly'][0]['target']);
        $this->assertSame(1000.0, (float) $report['quarterly'][0]['achieved']);
        $this->assertSame(1000.0, (float) $report['monthly'][1]['target']);
        $this->assertSame(100.0, (float) $report['monthly'][1]['attainment']);

        $sales = collect($report['by_sales'])->firstWhere('user_id', $rep->id);
        $this->assertNotNull($sales);
        $this->assertSame(6000.0, (float) $sales['target']);
        $this->assertSame(1000.0, (float) $sales['achieved']);

        $byBrand = collect($report['by_brand'])->firstWhere('brand_id', $brand->id);
        $this->assertSame(5000.0, (float) $byBrand['target']);
        $this->assertSame(1000.0, (float) $byBrand['achieved']);

        $byCustomer = collect($report['by_customer'])->firstWhere('account_id', $account->id);
        $this->assertSame(4000.0, (float) $byCustomer['target']);

        $byProduct = collect($report['by_product'])->firstWhere('product_id', $product->id);
        $this->assertSame(3000.0, (float) $byProduct['target']);
        $this->assertSame(1000.0, (float) $byProduct['achieved']);
    }

    public function test_it_splits_the_annual_target_when_period_targets_are_missing(): void
    {
        $year = now()->year;

        SalesTarget::create(['year' => $year, 'target_amount' => 1200]);

        $report = app(TargetAchievementService::class)->forYear($year);

        foreach ($report['monthly'] as $month) {
            $this->assertSame(100.0, (float) $month['target']);
        }

        foreach ($report['quarterly'] as $quarter) {
            $this->assertSame(300.0, (float) $quarter['target']);
        }

        $this->assertSame(1200.0, (float) $report['annual']['target']);
        $this->assertSame(0.0, (float) $report['annual']['achieved']);
    }
}
