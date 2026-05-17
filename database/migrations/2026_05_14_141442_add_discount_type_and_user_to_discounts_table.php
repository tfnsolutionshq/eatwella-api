<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            if (!Schema::hasColumn('discounts', 'discount_type')) {
                $table->enum('discount_type', ['menu', 'free_delivery'])->default('menu')->after('code');
            }
            if (!Schema::hasColumn('discounts', 'user_id')) {
                $table->uuid('user_id')->nullable()->after('used_count');
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['discount_type', 'user_id']);
        });
    }
};



