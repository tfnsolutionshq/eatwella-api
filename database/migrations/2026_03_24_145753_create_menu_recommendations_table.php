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
        Schema::create('menu_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignUuid('recommended_menu_id')->constrained('menus')->cascadeOnDelete();
            $table->string('algorithm')->default('hybrid'); // collaborative, content, hybrid
            $table->float('score')->default(0);
            $table->timestamps();
            
            $table->unique(['menu_id', 'recommended_menu_id', 'algorithm'], 'menu_rec_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_recommendations');
    }
};
