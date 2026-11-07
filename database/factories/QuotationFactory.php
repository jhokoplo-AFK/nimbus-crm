<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Enums\QuotationStatus;
use App\Models\Opportunity;
use App\Models\Pricebook;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'pricebook_id' => Pricebook::factory(),
            'quote_number' => 'Q-'.now()->format('Y').'-'.fake()->unique()->numerify('#####'),
            'revision_number' => 1,
            'is_primary' => true,
            'status' => QuotationStatus::Draft,
            'subtotal' => 0,
            'discount_type' => DiscountType::None,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 11,
            'tax_amount' => 0,
            'grand_total' => 0,
            'expiration_date' => now()->addDays(30)->toDateString(),
        ];
    }
}
