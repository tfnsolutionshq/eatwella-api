<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Menu extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'images',
        'is_available',
        'requires_takeaway',
        'stock_quantity',
    ];

    protected $casts = [
        'images'             => 'array',
        'requires_takeaway'  => 'boolean',
        'is_available'       => 'boolean',
        'stock_quantity'     => 'integer',
    ];

    // Accessor to get full image URLs
    public function getImagesAttribute($value)
    {
        $images = json_decode($value, true);
        if (!is_array($images)) {
            return [];
        }

        return array_map(function ($image) {
            return asset('storage/' . $image);
        }, $images);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function deductStock(int $quantity, ?int $userId = null): void
    {
        $before = $this->stock_quantity;
        $after  = max(0, $before - $quantity);

        $this->stock_quantity = $after;
        if ($after === 0) {
            $this->is_available = false;
        }
        $this->save();

        InventoryLog::create([
            'menu_id'          => $this->id,
            'user_id'          => $userId,
            'type'             => 'deduction',
            'quantity_before'  => $before,
            'quantity_changed' => -$quantity,
            'quantity_after'   => $after,
            'note'             => 'Order deduction',
        ]);
    }

    public function complements()
    {
        return $this->belongsToMany(Menu::class, 'menu_complements', 'menu_id', 'complementary_menu_id')
                    ->withPivot('is_active', 'sort_order')
                    ->withTimestamps()
                    ->orderByPivot('sort_order', 'asc');
    }
}
