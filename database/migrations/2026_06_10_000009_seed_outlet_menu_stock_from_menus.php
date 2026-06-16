<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $outlets = DB::table('outlets')->pluck('id');
        $menus   = DB::table('menus')->select('id', 'stock_quantity')->get();

        if ($outlets->isEmpty() || $menus->isEmpty()) {
            return;
        }

        $rows = [];
        $now  = now();

        foreach ($outlets as $outletId) {
            foreach ($menus as $menu) {
                $rows[] = [
                    'outlet_id'      => $outletId,
                    'menu_id'        => $menu->id,
                    'stock_quantity' => $menu->stock_quantity ?? 0,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        // Insert in chunks to avoid hitting MySQL's max packet size on large datasets
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('outlet_menu_stock')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        DB::table('outlet_menu_stock')->truncate();
    }
};
