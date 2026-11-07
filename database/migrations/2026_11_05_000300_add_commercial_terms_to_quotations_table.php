<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Sum of the per-line discounts. Subtotal stays net of these, so
            // the column keeps the audit trail the approval engine measures.
            $table->decimal('line_discount_amount', 15, 2)->default(0)->after('subtotal');
            $table->unsignedSmallInteger('payment_term_days')->default(30)->after('expiration_date');

            // Credit sign-off is evaluated on top of the price/term matrix so
            // an order cannot leave for ERP with an unapproved exposure.
            $table->string('credit_status')->default('Not Required')->index()->after('payment_term_days');
            $table->unsignedTinyInteger('credit_required_level')->nullable()->after('credit_status');
            $table->foreignId('credit_approved_by_id')->nullable()->after('credit_required_level')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('credit_approved_at')->nullable()->after('credit_approved_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_approved_by_id');
            $table->dropColumn([
                'line_discount_amount',
                'payment_term_days',
                'credit_status',
                'credit_required_level',
                'credit_approved_at',
            ]);
        });
    }
};
