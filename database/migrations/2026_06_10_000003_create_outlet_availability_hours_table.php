<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_availability_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->enum('day', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
            $table->boolean('enabled')->default(true);
            $table->string('open')->nullable();
            $table->string('close')->nullable();
            $table->json('order_types')->nullable();
            $table->timestamps();

            $table->unique(['outlet_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_availability_hours');
    }
};
