<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','confirmed','in_kitchen','ready','dispatched','completed','cancelled') DEFAULT 'pending'");

        Schema::table('orders', function (Blueprint $table) {
            $table->string('sent_to_kitchen_by_id')->nullable()->after('completed_by_id');
            $table->timestamp('sent_to_kitchen_at')->nullable()->after('sent_to_kitchen_by_id');
            $table->foreign('sent_to_kitchen_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['sent_to_kitchen_by_id']);
            $table->dropColumn(['sent_to_kitchen_by_id', 'sent_to_kitchen_at']);
        });

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','confirmed','ready','dispatched','completed','cancelled') DEFAULT 'pending'");
    }
};
