<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_sync_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['processing', 'completed'])->default('processing');
            $table->integer('total_orders')->default(0);
            $table->integer('synced_count')->default(0);
            $table->integer('reconciled_count')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_batches');
    }
};
