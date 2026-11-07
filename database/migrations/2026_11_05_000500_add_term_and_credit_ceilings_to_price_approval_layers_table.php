<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_approval_layers', function (Blueprint $table) {
            // A layer approves a quote only when the requested payment term
            // and the customer's resulting credit exposure also fit.
            $table->unsignedSmallInteger('max_payment_term_days')->nullable()->after('max_quote_amount');
            $table->decimal('max_credit_exposure', 15, 2)->nullable()->after('max_payment_term_days');
        });
    }

    public function down(): void
    {
        Schema::table('price_approval_layers', function (Blueprint $table) {
            $table->dropColumn(['max_payment_term_days', 'max_credit_exposure']);
        });
    }
};
