<?php

namespace App\Models;

use Database\Factories\SalesTargetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTarget extends Model
{
    /** @use HasFactory<SalesTargetFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'brand_id',
        'account_id',
        'product_id',
        'year',
        'quarter',
        'month',
        'target_amount',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'quarter' => 'integer',
            'month' => 'integer',
            'target_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Company-wide, undimensioned targets (the ones that drive the period
     * target vs achievement panels).
     */
    public function scopeCompanyWide(Builder $query): Builder
    {
        return $query->whereNull('user_id')
            ->whereNull('brand_id')
            ->whereNull('account_id')
            ->whereNull('product_id');
    }

    public function isCompanyWide(): bool
    {
        return $this->user_id === null
            && $this->brand_id === null
            && $this->account_id === null
            && $this->product_id === null;
    }

    public function isAnnual(): bool
    {
        return $this->quarter === null && $this->month === null;
    }

    public function periodLabel(): string
    {
        if ($this->month !== null) {
            return now()->setYear($this->year)->setMonth($this->month)->format('M Y');
        }

        if ($this->quarter !== null) {
            return "Q{$this->quarter} {$this->year}";
        }

        return (string) $this->year;
    }
}
