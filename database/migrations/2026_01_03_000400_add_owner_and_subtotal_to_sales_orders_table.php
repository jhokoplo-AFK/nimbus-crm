<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('opportunity_id');
            $table->decimal('subtotal', 15, 2)->default(0)->after('total_amount');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'order_date']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'order_date']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('subtotal');
        });
    }
};
