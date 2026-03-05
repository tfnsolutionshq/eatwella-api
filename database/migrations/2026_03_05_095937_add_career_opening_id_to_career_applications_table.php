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
        Schema::table('career_applications', function (Blueprint $table) {
            $table->foreignUuid('career_opening_id')
                ->after('id')
                ->constrained('career_openings')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('career_applications', function (Blueprint $table) {
            $table->dropForeign(['career_opening_id']);
            $table->dropColumn('career_opening_id');
        });
    }
};
