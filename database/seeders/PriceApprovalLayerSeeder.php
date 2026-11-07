<?php

namespace Database\Seeders;

use App\Models\PriceApprovalLayer;
use Illuminate\Database\Seeder;

/**
 * The default commercial ladder. Every value here is editable at runtime, so
 * the numbers are a starting policy rather than a hard-coded rule.
 */
class PriceApprovalLayerSeeder extends Seeder
{
    public function run(): void
    {
        $layers = [
            [
                'role_name' => 'Sales',
                'role_names' => ['Sales', 'Sales Rep'],
                'label' => 'Sales',
                'level' => 1,
                'max_discount_pct' => 5,
                'max_quote_amount' => 25000,
                'max_payment_term_days' => 30,
                'max_credit_exposure' => 25000,
            ],
            [
                'role_name' => 'Manager',
                'role_names' => ['Manager', 'Sales Manager'],
                'label' => 'Manager',
                'level' => 2,
                'max_discount_pct' => 10,
                'max_quote_amount' => 100000,
                'max_payment_term_days' => 45,
                'max_credit_exposure' => 100000,
            ],
            [
                'role_name' => 'Regional Manager',
                'role_names' => ['Regional Manager'],
                'label' => 'Regional Manager',
                'level' => 3,
                'max_discount_pct' => 15,
                'max_quote_amount' => 300000,
                'max_payment_term_days' => 60,
                'max_credit_exposure' => 300000,
            ],
            [
                'role_name' => 'General Manager',
                'role_names' => ['General Manager'],
                'label' => 'General Manager',
                'level' => 4,
                'max_discount_pct' => 20,
                'max_quote_amount' => 1000000,
                'max_payment_term_days' => 90,
                'max_credit_exposure' => 1000000,
            ],
            [
                'role_name' => 'Director',
                'role_names' => ['Director'],
                'label' => 'Director',
                'level' => 5,
                'max_discount_pct' => 100,
                'max_quote_amount' => null,
                'max_payment_term_days' => null,
                'max_credit_exposure' => null,
            ],
        ];

        foreach ($layers as $layer) {
            PriceApprovalLayer::query()->updateOrCreate(
                ['role_name' => $layer['role_name']],
                $layer + ['can_approve' => true, 'is_active' => true],
            );
        }
    }
}
