<?php

namespace App\Models;

use App\Models\Concerns\HasActivities;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasActivities, HasFactory;

    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'title',
        'email',
        'phone',
        'is_primary',
    ];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'primary_contact_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('first_name', 'like', "%{$term}%")
            ->orWhere('last_name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhereHas('account', fn (Builder $account) => $account->where('name', 'like', "%{$term}%")));
    }

    /**
     * Promote this contact to the account's primary contact, demoting peers.
     */
    public function makePrimary(): void
    {
        static::where('account_id', $this->account_id)
            ->whereKeyNot($this->getKey())
            ->update(['is_primary' => false]);

        $this->forceFill(['is_primary' => true])->save();
    }
}
