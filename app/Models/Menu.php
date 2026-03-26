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
    ];

    protected $casts = [
        'images'             => 'array',
        'requires_takeaway'  => 'boolean',
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

    public function complements()
    {
        return $this->belongsToMany(Menu::class, 'menu_complements', 'menu_id', 'complementary_menu_id')
                    ->withPivot('is_active', 'sort_order')
                    ->withTimestamps()
                    ->orderByPivot('sort_order', 'asc');
    }
}
