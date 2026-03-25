<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuRecommendation extends Model
{
    protected $fillable = [
        'menu_id',
        'recommended_menu_id',
        'algorithm',
        'score',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function recommendedMenu()
    {
        return $this->belongsTo(Menu::class, 'recommended_menu_id');
    }
}
