<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'cart_id',
        'menu_id',
        'quantity',
        'packaging_id',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function packaging(): BelongsTo
    {
        return $this->belongsTo(TakeawayPackaging::class, 'packaging_id');
    }
}
