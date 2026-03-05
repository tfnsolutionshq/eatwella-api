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
        // Modify enum to include 'loyalty_points'
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_type ENUM('cash', 'gateway', 'loyalty_points') NOT NULL DEFAULT 'gateway'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert enum to original
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_type ENUM('cash', 'gateway') NOT NULL DEFAULT 'gateway'");
    }
};
