<?php

namespace Database\Factories;

use App\Models\Pricebook;
use App\Models\PricebookEntry;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricebookEntry>
 */
class PricebookEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pricebook_id' => Pricebook::factory(),
            'product_id' => Product::factory(),
            'list_price' => fake()->randomFloat(2, 100, 25000),
        ];
    }
}
