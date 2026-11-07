<?php

namespace App\Models;

use Database\Factories\PricebookFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pricebook extends Model
{
    /** @use HasFactory<PricebookFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'is_standard',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_standard' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PricebookEntry::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
