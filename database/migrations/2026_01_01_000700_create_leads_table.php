<?php

use App\Enums\LeadStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('company')->nullable();
            $table->string('title')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('lead_source')->nullable();
            $table->string('status')->default(LeadStatus::New->value)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Conversion audit trail: each lead keeps pointers to the records
            // it produced so the relationship is traceable in both directions.
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('converted_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('converted_opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
