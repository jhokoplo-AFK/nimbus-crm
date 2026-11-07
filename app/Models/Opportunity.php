<?php

namespace App\Models;

use App\Enums\OpportunityStage;
use App\Models\Concerns\HasActivities;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use HasActivities, HasFactory;

    protected $fillable = [
        'account_id',
        'owner_id',
        'primary_contact_id',
        'name',
        'stage',
        'amount',
        'probability_pct',
        'close_date',
        'loss_reason',
    ];

    protected function casts(): array
    {
        return [
            'stage' => OpportunityStage::class,
            'amount' => 'decimal:2',
            'probability_pct' => 'integer',
            'close_date' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'primary_contact_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function primaryQuotation(): HasOne
    {
        return $this->hasOne(Quotation::class)->where('is_primary', true)->latestOfMany('revision_number');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', [
            OpportunityStage::ClosedWon->value,
            OpportunityStage::ClosedLost->value,
        ]);
    }

    public function scopeInStage(Builder $query, OpportunityStage|string $stage): Builder
    {
        return $query->where('stage', $stage instanceof OpportunityStage ? $stage->value : $stage);
    }

    public function scopeClosingBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('close_date', [$from, $to]);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === ''
            ? $query
            : $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhereHas('account', fn (Builder $account) => $account->where('name', 'like', "%{$term}%")));
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    public function isClosed(): bool
    {
        return $this->stage->isClosed();
    }

    /**
     * Expected value, used for the weighted pipeline KPI.
     */
    public function weightedAmount(): float
    {
        return round((float) $this->amount * ($this->probability_pct / 100), 2);
    }
}
