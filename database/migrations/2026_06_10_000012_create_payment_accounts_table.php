<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['transfer', 'pos', 'cash']);
            $table->string('label');                    // e.g. "GTBank", "OPay", "Cash"
            $table->string('account_name')->nullable(); // transfer: account name
            $table->string('account_number')->nullable(); // transfer/pos: account or terminal number
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
