<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['restock', 'deduction'])->index();
            $table->unsignedInteger('quantity_before');
            $table->integer('quantity_changed'); // positive = added, negative = deducted
            $table->unsignedInteger('quantity_after');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};
