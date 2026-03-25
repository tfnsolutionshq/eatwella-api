<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->boolean('requires_takeaway')->default(false)->after('is_available');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('takeaway_amount', 10, 2)->default(0)->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('requires_takeaway');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('takeaway_amount');
        });
    }
};
