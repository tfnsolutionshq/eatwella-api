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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (!Schema::hasColumn('users', 'birthday')) {
                $table->date('birthday')->nullable();
            }
            if (!Schema::hasColumn('users', 'street_address')) {
                $table->string('street_address')->nullable();
            }
            if (!Schema::hasColumn('users', 'state')) {
                $table->string('state')->nullable();
            }
            if (!Schema::hasColumn('users', 'closest_landmark')) {
                $table->string('closest_landmark')->nullable();
            }
            if (!Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'birthday', 'street_address', 'state', 'closest_landmark', 'postal_code']);
        });
    }
};



