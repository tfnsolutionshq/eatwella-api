<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutletMenuStock extends Model
{
    protected $table = 'outlet_menu_stock';

    protected $fillable = [
        'outlet_id',
        'menu_id',
        'stock_quantity',
    ];

    protected $casts = [
        'stock_quantity' => 'integer',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
