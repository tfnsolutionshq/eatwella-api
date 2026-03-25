<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('completed_by_id')->nullable()->after('delivery_note')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('completed_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['completed_by_id']);
            $table->dropColumn(['completed_by_id', 'completed_at']);
        });
    }
};
