<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('offline_id')->nullable()->unique()->after('id');
            $table->timestamp('offline_created_at')->nullable()->after('offline_id');
            $table->foreignUuid('sync_batch_id')->nullable()->constrained('offline_sync_batches')->nullOnDelete()->after('outlet_id');
            $table->json('sync_reconciliation')->nullable()->after('sync_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['sync_batch_id']);
            $table->dropColumn(['offline_id', 'offline_created_at', 'sync_batch_id', 'sync_reconciliation']);
        });
    }
};
