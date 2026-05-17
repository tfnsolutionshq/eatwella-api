<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_type ENUM('cash', 'pos', 'transfer', 'gateway', 'loyalty_points') NOT NULL DEFAULT 'gateway'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_type ENUM('cash', 'gateway', 'loyalty_points') NOT NULL DEFAULT 'gateway'");
    }
};



