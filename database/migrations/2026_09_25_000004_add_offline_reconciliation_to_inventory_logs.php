<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE inventory_logs MODIFY COLUMN type ENUM('restock', 'deduction', 'offline_reconciliation') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE inventory_logs MODIFY COLUMN type ENUM('restock', 'deduction') NOT NULL");
    }
};
