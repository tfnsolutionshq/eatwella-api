<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'menu_id',
        'quantity',
        'price',
        'subtotal',
        'packaging_id',
        'packaging_price',
    ];

    protected $casts = [
        'price' => 'float',
        'subtotal' => 'float',
        'packaging_price' => 'float',
    ];

    public function packaging()
    {
        return $this->belongsTo(TakeawayPackaging::class, 'packaging_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
