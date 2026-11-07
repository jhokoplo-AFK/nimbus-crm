<?php

namespace Tests\Feature;

use App\Enums\QuotationStatus;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\PriceApprovalLayer;
use App\Models\Pricebook;
use App\Models\PricebookEntry;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\QuotationService;
use Database\Seeders\PriceApprovalLayerSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PriceApprovalLayerTest extends TestCase
{
    use RefreshDatabase;

    private Opportunity $opportunity;

    private Pricebook $pricebook;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PriceApprovalLayerSeeder::class);

        $account = Account::factory()->create();
        $this->opportunity = Opportunity::factory()->for($account)->create();
        $this->pricebook = Pricebook::factory()->standard()->create();
        $this->product = Product::factory()->create();

        PricebookEntry::factory()->create([
            'pricebook_id' => $this->pricebook->id,
            'product_id' => $this->product->id,
            'list_price' => 1200,
        ]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function quoteFor(User $actor, array $overrides = []): Quotation
    {
        return app(QuotationService::class)->create($this->opportunity, $this->pricebook, array_merge([
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'discount_pct' => 0],
            ],
            'discount_type' => 'none',
            'discount_value' => 0,
            'tax_rate' => 11,
        ], $overrides), $actor);
    }

    public function test_a_quote_inside_the_sales_layer_stays_draft(): void
    {
        $quotation = $this->quoteFor($this->user('Sales'));

        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertNull($quotation->required_approval_level);
    }

    public function test_an_eight_percent_discount_routes_to_the_manager_layer(): void
    {
        $sales = $this->user('Sales');

        $quotation = $this->quoteFor($sales, [
            'discount_type' => 'percent',
            'discount_value' => 8,
        ]);

        $this->assertSame(QuotationStatus::InReview, $quotation->status);
        $this->assertSame(2, $quotation->required_approval_level);

        $manager = $this->user('Manager');

        $this->actingAs($manager)
            ->post(route('quotations.approve', $quotation))
            ->assertRedirect();

        $this->assertSame(QuotationStatus::Approved, $quotation->refresh()->status);
    }

    public function test_an_eighteen_percent_discount_cannot_be_approved_by_a_manager(): void
    {
        $quotation = $this->quoteFor($this->user('Sales'), [
            'discount_type' => 'percent',
            'discount_value' => 18,
        ]);

        $this->assertSame(QuotationStatus::InReview, $quotation->status);
        $this->assertSame(4, $quotation->required_approval_level);

        $manager = $this->user('Manager');

        $this->actingAs($manager)
            ->post(route('quotations.approve', $quotation))
            ->assertForbidden();

        $this->assertSame(QuotationStatus::InReview, $quotation->refresh()->status);

        $generalManager = $this->user('General Manager');

        $this->actingAs($generalManager)
            ->post(route('quotations.approve', $quotation))
            ->assertRedirect();

        $this->assertSame(QuotationStatus::Approved, $quotation->refresh()->status);
        $this->assertSame($generalManager->id, $quotation->approved_by_id);
    }

    public function test_value_alone_escalates_the_approval_layer(): void
    {
        PricebookEntry::query()
            ->where('pricebook_id', $this->pricebook->id)
            ->where('product_id', $this->product->id)
            ->update(['list_price' => 400000]);

        $quotation = $this->quoteFor($this->user('Sales'));

        // 444,000 sits above the Regional Manager ceiling (300,000).
        $this->assertSame(QuotationStatus::InReview, $quotation->status);
        $this->assertSame(4, $quotation->required_approval_level);
    }

    public function test_the_director_layer_is_unbounded(): void
    {
        $quotation = $this->quoteFor($this->user('Sales'), [
            'discount_type' => 'percent',
            'discount_value' => 60,
        ]);

        $this->assertSame(5, $quotation->required_approval_level);

        $director = $this->user('Director');

        $this->actingAs($director)
            ->post(route('quotations.approve', $quotation))
            ->assertRedirect();

        $this->assertSame(QuotationStatus::Approved, $quotation->refresh()->status);
    }

    public function test_limits_are_parameterised_so_a_layer_can_be_retuned_at_runtime(): void
    {
        PriceApprovalLayer::query()
            ->where('role_name', 'Sales')
            ->update(['max_discount_pct' => 25]);

        $quotation = $this->quoteFor($this->user('Sales'), [
            'discount_type' => 'percent',
            'discount_value' => 20,
        ]);

        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertNull($quotation->required_approval_level);
    }

    public function test_only_approval_admins_can_tune_the_matrix_over_the_api(): void
    {
        $layer = PriceApprovalLayer::query()->where('role_name', 'Manager')->firstOrFail();

        Sanctum::actingAs($this->user('Sales'));

        $this->getJson('/api/price-approval-layers')->assertForbidden();
        $this->patchJson("/api/price-approval-layers/{$layer->id}", ['max_discount_pct' => 12])
            ->assertForbidden();

        Sanctum::actingAs($this->user('General Manager'));

        $this->getJson('/api/price-approval-layers')
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $this->patchJson("/api/price-approval-layers/{$layer->id}", ['max_discount_pct' => 12])
            ->assertOk()
            ->assertJsonPath('max_discount_pct', '12.00');
    }
}
