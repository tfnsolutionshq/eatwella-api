<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_proof_image')) {
                $table->string('delivery_proof_image')->nullable()->after('assigned_at');
            }
            if (!Schema::hasColumn('orders', 'delivery_note')) {
                $table->text('delivery_note')->nullable()->after('delivery_proof_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_proof_image', 'delivery_note']);
        });
    }
};



