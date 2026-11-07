<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_approval_layers', function (Blueprint $table) {
            $table->id();

            // Canonical role that owns this layer, plus every role mapped to it
            // so legacy role names keep working while the matrix is migrated.
            $table->string('role_name')->unique();
            $table->json('role_names');
            $table->string('label');

            // 1 = Sales, 2 = Manager, 3 = Regional Manager, 4 = General
            // Manager, 5 = Director. Higher levels carry more authority.
            $table->unsignedTinyInteger('level')->unique();

            // A quote must fit inside both limits to be signed off at this
            // layer. A null amount means an unlimited ceiling (board level).
            $table->decimal('max_discount_pct', 5, 2)->default(0);
            $table->decimal('max_quote_amount', 15, 2)->nullable();

            $table->boolean('can_approve')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_approval_layers');
    }
};
