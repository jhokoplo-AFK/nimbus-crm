<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Null means no limit has been agreed for the customer, which is
            // different from a zero limit that blocks every new order.
            $table->decimal('credit_limit', 15, 2)->nullable()->after('annual_revenue');
            $table->decimal('balance_due', 15, 2)->default(0)->after('credit_limit');
            $table->decimal('overdue_balance', 15, 2)->default(0)->after('balance_due');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['credit_limit', 'balance_due', 'overdue_balance']);
        });
    }
};
