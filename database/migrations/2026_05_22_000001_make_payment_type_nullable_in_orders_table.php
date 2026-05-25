<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_type ENUM('cash', 'pos', 'transfer', 'gateway', 'loyalty_points') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE orders SET payment_type = 'gateway' WHERE payment_type IS NULL");
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_type ENUM('cash', 'pos', 'transfer', 'gateway', 'loyalty_points') NOT NULL DEFAULT 'gateway'");
    }
};
