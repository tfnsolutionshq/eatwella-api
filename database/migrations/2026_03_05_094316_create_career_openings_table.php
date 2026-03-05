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
        Schema::create('career_openings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('role')->index();
            $table->string('location')->nullable();
            $table->string('employment_type')->nullable();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('closes_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('career_openings');
    }
};
