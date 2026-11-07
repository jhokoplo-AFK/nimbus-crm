<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('account_id')->constrained('users')->nullOnDelete();
            $table->index(['owner_id', 'close_date']);
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['owner_id', 'close_date']);
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
