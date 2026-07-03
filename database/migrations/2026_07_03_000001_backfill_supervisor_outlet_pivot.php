<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill outlet_user pivot for supervisors that have outlet_id set
        $supervisors = DB::table('users')
            ->where('role', 'supervisor')
            ->whereNotNull('outlet_id')
            ->get(['id', 'outlet_id']);

        foreach ($supervisors as $supervisor) {
            $exists = DB::table('outlet_user')
                ->where('outlet_id', $supervisor->outlet_id)
                ->where('user_id', $supervisor->id)
                ->exists();

            if (! $exists) {
                DB::table('outlet_user')->insert([
                    'outlet_id' => $supervisor->outlet_id,
                    'user_id'   => $supervisor->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Not reversible — we don't want to delete pivot rows on rollback
    }
};
