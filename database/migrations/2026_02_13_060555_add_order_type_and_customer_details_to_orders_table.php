<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'order_type')) {
                $table->enum('order_type', ['dine', 'pickup', 'delivery'])->after('order_number');
            }
            if (!Schema::hasColumn('orders', 'customer_name')) {
                $table->string('customer_name')->after('customer_email');
            }
            if (!Schema::hasColumn('orders', 'customer_phone')) {
                $table->string('customer_phone')->nullable()->after('customer_name');
            }
            if (!Schema::hasColumn('orders', 'table_number')) {
                $table->string('table_number')->nullable()->after('customer_phone');
            }
            if (!Schema::hasColumn('orders', 'delivery_address')) {
                $table->text('delivery_address')->nullable()->after('table_number');
            }
            if (!Schema::hasColumn('orders', 'delivery_city')) {
                $table->string('delivery_city')->nullable()->after('delivery_address');
            }
            if (!Schema::hasColumn('orders', 'delivery_zip')) {
                $table->string('delivery_zip')->nullable()->after('delivery_city');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['order_type', 'customer_name', 'customer_phone', 'table_number', 'delivery_address', 'delivery_city', 'delivery_zip']);
        });
    }
};



