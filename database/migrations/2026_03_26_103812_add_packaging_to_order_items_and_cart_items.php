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
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignUuid('packaging_id')->nullable()->constrained('takeaway_packagings')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignUuid('packaging_id')->nullable()->constrained('takeaway_packagings')->nullOnDelete();
            $table->decimal('packaging_price', 10, 2)->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['packaging_id']);
            $table->dropColumn(['packaging_id', 'packaging_price']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['packaging_id']);
            $table->dropColumn('packaging_id');
        });
    }
};
