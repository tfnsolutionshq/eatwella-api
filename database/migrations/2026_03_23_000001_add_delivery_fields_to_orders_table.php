<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_fee')) {
                $table->decimal('delivery_fee', 10, 2)->default(0)->after('tax_amount');
            }
            if (!Schema::hasColumn('orders', 'delivery_agent_id')) {
                $table->foreignUuid('delivery_agent_id')->nullable()->after('cashier_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'assigned_by_supervisor_id')) {
                $table->foreignUuid('assigned_by_supervisor_id')->nullable()->after('delivery_agent_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_by_supervisor_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_agent_id']);
            $table->dropForeign(['assigned_by_supervisor_id']);
            $table->dropColumn(['delivery_fee', 'delivery_agent_id', 'assigned_by_supervisor_id', 'assigned_at']);
        });
    }
};



