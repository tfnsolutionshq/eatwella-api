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
        Schema::create('taxes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type')->default('VAT');
            $table->text('description')->nullable();
            $table->decimal('rate', 8, 2);
            $table->integer('priority')->default(0);
            $table->boolean('is_inclusive')->default(false); // false = exclusive, true = inclusive
            $table->json('branches')->nullable(); // Stores applicable branches data
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('category_tax', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tax_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_tax');
        Schema::dropIfExists('taxes');
    }
};
