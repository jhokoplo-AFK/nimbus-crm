<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Lowest authority layer allowed to sign this quote off. Null means
            // the quote did not need a layered approval when it was priced.
            $table->unsignedTinyInteger('required_approval_level')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('required_approval_level');
        });
    }
};
