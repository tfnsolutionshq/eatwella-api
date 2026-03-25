<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationLog extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'recommended_menu_id',
        'ab_test_group',
        'action',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recommendedMenu()
    {
        return $this->belongsTo(Menu::class, 'recommended_menu_id');
    }
}
