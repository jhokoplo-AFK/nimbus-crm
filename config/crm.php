<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sales Policy
    |--------------------------------------------------------------------------
    |
    | Central place for the commercial rules the quotation engine enforces.
    | Keeping them in configuration (instead of scattered constants) lets each
    | environment tighten or relax approval thresholds without a deployment.
    |
    */

    'currency' => env('CRM_CURRENCY', 'USD'),

    'tax_rate' => (float) env('CRM_TAX_RATE', 11),

    'approval' => [
        // A quote whose effective discount exceeds this % requires sign-off.
        'discount_threshold_pct' => (float) env('CRM_DISCOUNT_APPROVAL_THRESHOLD_PCT', 15),

        // A quote above this grand total requires sign-off.
        'amount_threshold' => (float) env('CRM_QUOTE_APPROVAL_THRESHOLD_AMOUNT', 50000),

        // Payment terms longer than this need sign-off when the layers have
        // not been seeded (the matrix normally carries the real ceilings).
        'payment_term_days' => (int) env('CRM_APPROVAL_PAYMENT_TERM_DAYS', 30),
    ],

    'credit' => [
        // A breached limit or an overdue invoice is never self-approved by the
        // sales layer; at least a manager must sign the exposure off.
        'minimum_approval_level' => (int) env('CRM_CREDIT_MIN_APPROVAL_LEVEL', 2),
    ],

    'documents' => [
        'quote_prefix' => 'Q',
        'order_prefix' => 'SO',
        'contract_prefix' => 'CT',
        'number_padding' => 5,
    ],

    // Probability assigned to an opportunity when it enters a stage.
    'default_close_window_days' => 30,

];
