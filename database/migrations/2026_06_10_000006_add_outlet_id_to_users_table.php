<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasFk = collect(Schema::getForeignKeys('users'))
                    ->contains(fn($fk) => in_array('outlet_id', $fk['columns']));

        if ($hasFk) {
            return;
        }

        if (!Schema::hasColumn('users', 'outlet_id')) {
            // Match exact collation of outlets.id to satisfy strict MySQL FK check
            DB::statement('ALTER TABLE `users` ADD COLUMN `outlet_id` char(36) COLLATE utf8mb4_unicode_ci NULL AFTER `role`');
        } else {
            // Column exists but wrong collation — fix it first
            DB::statement('ALTER TABLE `users` MODIFY COLUMN `outlet_id` char(36) COLLATE utf8mb4_unicode_ci NULL');
        }

        DB::statement('ALTER TABLE `users` ADD CONSTRAINT `users_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'outlet_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropColumn('outlet_id');
        });
    }
};
