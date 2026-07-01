<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            $table->dropColumn('state');
            $table->foreignId('city_id')->nullable()->after('phone')->constrained('cities')->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->after('city_id')->constrained('zones')->nullOnDelete();
            $table->string('street_address')->nullable()->after('zone_id');
            $table->string('profile_picture')->nullable()->after('vehicle_type');
        });
    }

    public function down(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['zone_id']);
            $table->dropColumn(['city_id', 'zone_id', 'profile_picture']);
            $table->string('state')->nullable();
        });
    }
};
