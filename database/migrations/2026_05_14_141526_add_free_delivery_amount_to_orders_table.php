<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Stores the actual delivery fee when a free_delivery discount is applied.
            // delivery_fee on the order will be 0 for the customer; this preserves the real amount for the rider/admin.
            if (!Schema::hasColumn('orders', 'free_delivery_amount')) {
                $table->decimal('free_delivery_amount', 10, 2)->default(0)->after('delivery_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('free_delivery_amount');
        });
    }
};



