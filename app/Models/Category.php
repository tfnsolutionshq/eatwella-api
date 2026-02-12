<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = ['name', 'description', 'is_active'];

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }
}
