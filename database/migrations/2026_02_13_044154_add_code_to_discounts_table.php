<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            if (!Schema::hasColumn('discounts', 'code')) {
                $table->string('code')->unique()->after('name');
            }
            if (!Schema::hasColumn('discounts', 'usage_limit')) {
                $table->integer('usage_limit')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('discounts', 'used_count')) {
                $table->integer('used_count')->default(0)->after('usage_limit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn(['code', 'usage_limit', 'used_count']);
        });
    }
};



