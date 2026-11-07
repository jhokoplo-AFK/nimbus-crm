<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One rung of the price approval matrix. Limits are data, not code: an admin
 * can tighten the Sales ceiling without shipping a release, and every quote is
 * re-evaluated against the current matrix when it is priced.
 */
class PriceApprovalLayer extends Model
{
    protected $fillable = [
        'role_name',
        'role_names',
        'label',
        'level',
        'max_discount_pct',
        'max_quote_amount',
        'max_payment_term_days',
        'max_credit_exposure',
        'can_approve',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'role_names' => 'array',
            'level' => 'integer',
            'max_discount_pct' => 'decimal:2',
            'max_quote_amount' => 'decimal:2',
            'max_payment_term_days' => 'integer',
            'max_credit_exposure' => 'decimal:2',
            'can_approve' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('level');
    }

    public function appliesToRole(string $role): bool
    {
        return $this->role_name === $role
            || in_array($role, $this->role_names ?? [], true);
    }

    /**
     * A layer may sign a quote only when the discount, the quote value and the
     * requested payment term all fit under its ceilings.
     */
    public function covers(float $discountPct, float $grandTotal, ?int $paymentTermDays = null): bool
    {
        $withinDiscount = round($discountPct, 2) <= round((float) $this->max_discount_pct, 2);
        $withinAmount = $this->max_quote_amount === null
            || round($grandTotal, 2) <= round((float) $this->max_quote_amount, 2);
        $withinTerm = $paymentTermDays === null
            || $this->max_payment_term_days === null
            || $paymentTermDays <= $this->max_payment_term_days;

        return $withinDiscount && $withinAmount && $withinTerm;
    }

    public function isUnlimited(): bool
    {
        return (float) $this->max_discount_pct >= 100 && $this->max_quote_amount === null;
    }
}
