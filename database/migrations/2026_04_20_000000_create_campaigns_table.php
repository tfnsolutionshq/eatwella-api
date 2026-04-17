<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('details');
            $table->string('image_path')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['published', 'drafted'])->default('drafted')->index();
            $table->string('url')->nullable();
            $table->enum('type', ['modal', 'banner'])->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
