<?php

use App\Enums\ActivityStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('type')->index();
            $table->date('due_date')->nullable();
            $table->string('status')->default(ActivityStatus::Open->value)->index();
            $table->morphs('relatable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['relatable_type', 'relatable_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
