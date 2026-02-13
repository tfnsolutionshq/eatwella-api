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
            $table->enum('order_type', ['dine', 'pickup', 'delivery'])->after('order_number');
            $table->string('customer_name')->after('customer_email');
            $table->string('customer_phone')->nullable()->after('customer_name');
            $table->string('table_number')->nullable()->after('customer_phone');
            $table->text('delivery_address')->nullable()->after('table_number');
            $table->string('delivery_city')->nullable()->after('delivery_address');
            $table->string('delivery_zip')->nullable()->after('delivery_city');
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
