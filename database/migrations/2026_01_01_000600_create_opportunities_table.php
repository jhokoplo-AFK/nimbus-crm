<?php

use App\Enums\OpportunityStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('primary_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('name');
            $table->string('stage')->default(OpportunityStage::Prospecting->value)->index();
            $table->decimal('amount', 15, 2)->default(0);
            $table->integer('probability_pct')->default(10);
            $table->date('close_date')->nullable();
            $table->string('loss_reason')->nullable();
            $table->timestamps();

            $table->index(['stage', 'close_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
