<?php

namespace Tests\Feature;

use App\Enums\QuotationStatus;
use App\Exceptions\PricebookNotAvailableException;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Pricebook;
use App\Models\PricebookEntry;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $rep;

    private Opportunity $opportunity;

    private Pricebook $pricebook;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rep = User::factory()->create();

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

    private function makeQuote(array $overrides = []): Quotation
    {
        return app(QuotationService::class)->create($this->opportunity, $this->pricebook, array_merge([
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 1, 'discount_pct' => 0],
            ],
            'discount_type' => 'none',
            'discount_value' => 0,
            'tax_rate' => 11,
        ], $overrides), $this->rep);
    }

    public function test_prices_are_taken_from_the_pricebook_and_ignore_client_values(): void
    {
        $quotation = $this->makeQuote();

        $this->assertEqualsWithDelta(1200.0, (float) $quotation->lineItems->first()->unit_price, 0.001);
        $this->assertEqualsWithDelta(2400.0, (float) $quotation->subtotal, 0.001);
        $this->assertEqualsWithDelta(2664.0, (float) $quotation->grand_total, 0.001);
        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertTrue($quotation->is_primary);
    }

    public function test_a_product_missing_from_the_pricebook_is_rejected(): void
    {
        $otherProduct = Product::factory()->create();

        $this->expectException(PricebookNotAvailableException::class);

        $this->makeQuote([
            'lines' => [['product_id' => $otherProduct->id, 'quantity' => 1]],
        ]);
    }

    public function test_discount_above_fifteen_percent_enters_review(): void
    {
        $quotation = $this->makeQuote([
            'discount_type' => 'percent',
            'discount_value' => 20,
        ]);

        $this->assertSame(QuotationStatus::InReview, $quotation->status);
        $this->assertEqualsWithDelta(480.0, (float) $quotation->discount_amount, 0.001);
        $this->assertEqualsWithDelta(2131.2, (float) $quotation->grand_total, 0.001);
    }

    public function test_a_quote_above_fifty_thousand_enters_review(): void
    {
        PricebookEntry::query()
            ->where('pricebook_id', $this->pricebook->id)
            ->where('product_id', $this->product->id)
            ->update(['list_price' => 60000]);

        $quotation = $this->makeQuote();

        $this->assertSame(QuotationStatus::InReview, $quotation->status);
    }

    public function test_revising_clones_lines_increments_revision_and_demotes_the_previous_quote(): void
    {
        $original = $this->makeQuote();

        $revision = app(QuotationService::class)->revise($original, $this->rep);

        $this->assertSame(2, $revision->revision_number);
        $this->assertTrue($revision->is_primary);
        $this->assertFalse($original->refresh()->is_primary);
        $this->assertSame(QuotationStatus::Draft, $revision->status);
        $this->assertCount(1, $revision->lineItems);
        $this->assertEqualsWithDelta(2400.0, (float) $revision->subtotal, 0.001);
        $this->assertStringEndsWith('-R2', $revision->quote_number);
        $this->assertNotSame($original->quote_number, $revision->quote_number);
    }

    public function test_pricebook_without_the_product_cannot_be_used_for_an_update(): void
    {
        $quotation = $this->makeQuote();
        $emptyBook = Pricebook::factory()->create();

        $this->expectException(PricebookNotAvailableException::class);

        app(QuotationService::class)->update($quotation, [
            'pricebook_id' => $emptyBook->id,
            'lines' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'discount_type' => 'none',
            'discount_value' => 0,
        ], $this->rep);
    }
}
