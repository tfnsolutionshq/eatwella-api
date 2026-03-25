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
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'cashier', 'customer', 'supervisor', 'delivery_agent', 'kitchen') DEFAULT 'customer'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'confirmed', 'ready', 'dispatched', 'completed', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Careful with down migrations for ENUMs as they can cause data truncation if rows exist with the new values.
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'cashier', 'customer', 'supervisor', 'delivery_agent') DEFAULT 'customer'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending'");
    }
};
