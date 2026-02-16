<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasUuids;

    protected $fillable = ['session_id', 'user_id', 'discount_code'];

    protected $appends = ['subtotal', 'discount_amount', 'total', 'total_items'];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class, 'discount_code', 'code');
    }

    public function getSubtotalAttribute()
    {
        return $this->items->sum(function ($item) {
            return $item->menu->price * $item->quantity;
        });
    }

    public function getDiscountAmountAttribute()
    {
        if (!$this->discount_code) return 0;
        
        $discount = $this->discount;
        if (!$discount || !$discount->isValid()) return 0;
        
        return $discount->calculateDiscount($this->subtotal);
    }

    public function getTotalAttribute()
    {
        return max(0, $this->subtotal - $this->discount_amount);
    }

    public function getTotalItemsAttribute()
    {
        return $this->items->sum('quantity');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
