<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasFk = collect(Schema::getForeignKeys('orders'))
                    ->contains(fn($fk) => in_array('outlet_id', $fk['columns']));

        if ($hasFk) {
            return;
        }

        if (!Schema::hasColumn('orders', 'outlet_id')) {
            DB::statement('ALTER TABLE `orders` ADD COLUMN `outlet_id` char(36) COLLATE utf8mb4_unicode_ci NULL AFTER `id`');
        } else {
            DB::statement('ALTER TABLE `orders` MODIFY COLUMN `outlet_id` char(36) COLLATE utf8mb4_unicode_ci NULL');
        }

        DB::statement('ALTER TABLE `orders` ADD CONSTRAINT `orders_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('orders', 'outlet_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropColumn('outlet_id');
        });
    }
};
