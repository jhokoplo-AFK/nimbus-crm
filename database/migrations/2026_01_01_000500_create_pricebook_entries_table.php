<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricebook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricebook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('list_price', 15, 2);
            $table->timestamps();

            // A product may only appear once per pricebook: this is the
            // constraint the quotation engine relies on to resolve a price.
            $table->unique(['pricebook_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricebook_entries');
    }
};
