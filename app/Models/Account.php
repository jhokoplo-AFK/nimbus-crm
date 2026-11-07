<?php

namespace App\Models;

use App\Models\Concerns\HasActivities;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasActivities, HasFactory;

    protected $fillable = [
        'name',
        'industry',
        'annual_revenue',
        'credit_limit',
        'balance_due',
        'overdue_balance',
        'phone',
        'website',
        'billing_address',
        'shipping_address',
    ];

    protected function casts(): array
    {
        return [
            'annual_revenue' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'overdue_balance' => 'decimal:2',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(Contact::class)->where('is_primary', true);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /**
     * Remaining headroom before new business breaches the agreed limit. Null
     * means no limit has been agreed, which is not the same as zero headroom.
     */
    public function availableCredit(): ?float
    {
        return $this->credit_limit === null
            ? null
            : round((float) $this->credit_limit - (float) $this->balance_due, 2);
    }

    public function openOpportunities(): HasMany
    {
        return $this->opportunities()->whereNotIn('stage', ['Closed Won', 'Closed Lost']);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === ''
            ? $query
            : $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('industry', 'like', "%{$term}%")
                ->orWhere('website', 'like', "%{$term}%"));
    }

    public function scopeWithIndustry(Builder $query, ?string $industry): Builder
    {
        return $industry ? $query->where('industry', $industry) : $query;
    }

    /**
     * The headline number shown on the account record header.
     */
    public function openPipelineValue(): float
    {
        return (float) $this->openOpportunities()->sum('amount');
    }
}
