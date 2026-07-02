<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('init_payment','pending','processing','confirmed','in_kitchen','ready','dispatched','completed','cancelled') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','confirmed','in_kitchen','ready','dispatched','completed','cancelled') DEFAULT 'pending'");
    }
};
