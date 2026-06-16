<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_menu_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignUuid('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->integer('stock_quantity')->default(0);
            $table->timestamps();

            $table->unique(['outlet_id', 'menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_menu_stock');
    }
};
