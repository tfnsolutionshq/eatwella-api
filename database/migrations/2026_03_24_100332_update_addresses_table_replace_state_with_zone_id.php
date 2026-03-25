<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clear existing addresses since the old state/postal_code fields
        // cannot be automatically mapped to zone_id
        DB::table('addresses')->truncate();

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['state', 'postal_code']);
            $table->foreignId('zone_id')->after('street_address')->constrained('zones')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
            $table->dropColumn('zone_id');
            $table->string('state')->after('street_address');
            $table->string('postal_code')->nullable();
        });
    }
};
