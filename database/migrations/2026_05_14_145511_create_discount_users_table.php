<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createIfNotExists('discount_user', function (Blueprint $table) {
            $table->uuid('discount_id');
            $table->uuid('user_id');
            $table->primary(['discount_id', 'user_id']);
            $table->foreign('discount_id')->references('id')->on('discounts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_user');
    }
};



