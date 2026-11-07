<?php

namespace App\Models;

use App\Enums\CreditStatus;
use App\Enums\DiscountType;
use App\Enums\QuotationStatus;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    protected $fillable = [
        'opportunity_id',
        'pricebook_id',
        'quote_number',
        'revision_number',
        'is_primary',
        'status',
        'required_approval_level',
        'subtotal',
        'line_discount_amount',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'grand_total',
        'expiration_date',
        'payment_term_days',
        'credit_status',
        'credit_required_level',
        'credit_approved_by_id',
        'credit_approved_at',
        'approved_by_id',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'required_approval_level' => 'integer',
            'credit_status' => CreditStatus::class,
            'credit_required_level' => 'integer',
            'payment_term_days' => 'integer',
            'discount_type' => DiscountType::class,
            'is_primary' => 'boolean',
            'revision_number' => 'integer',
            'subtotal' => 'decimal:2',
            'line_discount_amount' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'expiration_date' => 'date',
            'approved_at' => 'datetime',
            'credit_approved_at' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function pricebook(): BelongsTo
    {
        return $this->belongsTo(Pricebook::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class)->orderBy('id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function creditApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credit_approved_by_id');
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeStatus(Builder $query, QuotationStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof QuotationStatus ? $status->value : $status);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === QuotationStatus::InReview;
    }

    public function canBeRevised(): bool
    {
        return ! in_array($this->status, [QuotationStatus::Approved], true);
    }

    /**
     * Effective discount as a percentage of the gross price. Both line-level
     * and quote-level discounts count, which is what stops a sales rep from
     * hiding a large line discount behind a small quote-wide one.
     */
    public function effectiveDiscountPct(): float
    {
        $lineDiscount = (float) $this->line_discount_amount;
        $gross = (float) $this->subtotal + $lineDiscount;

        if ($gross <= 0) {
            return 0.0;
        }

        return round((($lineDiscount + (float) $this->discount_amount) / $gross) * 100, 2);
    }
}
