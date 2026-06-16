<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $fillable = [
        'city_id',
        'name',
        'is_active',
        'delivery_fee',
        'sort_order',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'delivery_fee' => 'decimal:2',
        'sort_order'   => 'integer',
        'latitude'     => 'float',
        'longitude'    => 'float',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
