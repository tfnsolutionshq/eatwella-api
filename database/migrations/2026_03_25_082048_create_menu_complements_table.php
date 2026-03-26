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
        Schema::create('menu_complements', function (Blueprint $table) {
            $table->foreignUuid('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignUuid('complementary_menu_id')->constrained('menus')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['menu_id', 'complementary_menu_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_complements');
    }
};
