<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add 'attendant' to ENUM temporarily
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'cashier', 'attendant', 'customer', 'supervisor', 'delivery_agent', 'kitchen') DEFAULT 'customer'");

        // 2. Update existing data
        DB::table('users')->where('role', 'cashier')->update(['role' => 'attendant']);

        // 3. Remove 'cashier' from ENUM
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'attendant', 'customer', 'supervisor', 'delivery_agent', 'kitchen') DEFAULT 'customer'");

        // 4. Rename cashier_id to attendant_id in orders
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['cashier_id']);
            $table->renameColumn('cashier_id', 'attendant_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('attendant_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['attendant_id']);
            $table->renameColumn('attendant_id', 'cashier_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('cashier_id')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'cashier', 'attendant', 'customer', 'supervisor', 'delivery_agent', 'kitchen') DEFAULT 'customer'");
        DB::table('users')->where('role', 'attendant')->update(['role' => 'cashier']);
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'cashier', 'customer', 'supervisor', 'delivery_agent', 'kitchen') DEFAULT 'customer'");
    }
};
