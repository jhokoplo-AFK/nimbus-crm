<?php

namespace App\Models;

use Database\Factories\PricebookEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricebookEntry extends Model
{
    /** @use HasFactory<PricebookEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'pricebook_id',
        'product_id',
        'list_price',
    ];

    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
        ];
    }

    public function pricebook(): BelongsTo
    {
        return $this->belongsTo(Pricebook::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
