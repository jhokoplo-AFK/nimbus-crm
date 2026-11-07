<?php

namespace Database\Factories;

use App\Enums\SalesOrderStatus;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 1000, 200000);

        return [
            'contract_id' => null,
            'account_id' => Account::factory(),
            'opportunity_id' => Opportunity::factory(),
            'user_id' => null,
            'order_number' => 'SO-'.now()->format('Y').'-'.fake()->unique()->numerify('#####'),
            'order_date' => now()->toDateString(),
            'subtotal' => $subtotal,
            'total_amount' => round($subtotal * 1.11, 2),
            'status' => SalesOrderStatus::Draft,
        ];
    }
}
