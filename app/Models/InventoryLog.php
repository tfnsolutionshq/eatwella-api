<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'menu_id',
        'user_id',
        'type',
        'quantity_before',
        'quantity_changed',
        'quantity_after',
        'note',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
