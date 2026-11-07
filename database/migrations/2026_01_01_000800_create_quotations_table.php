<?php

use App\Enums\DiscountType;
use App\Enums\QuotationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pricebook_id')->constrained()->restrictOnDelete();
            $table->string('quote_number')->unique();
            $table->unsignedInteger('revision_number')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default(QuotationStatus::Draft->value)->index();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('discount_type')->default(DiscountType::None->value);
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(11);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            $table->date('expiration_date')->nullable();

            // Approval workflow audit trail.
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->timestamps();

            // An opportunity can carry several quotes; revisions are per quote.
            $table->index(['opportunity_id', 'is_primary']);
            $table->index(['opportunity_id', 'revision_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
