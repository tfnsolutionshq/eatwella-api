<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutletAvailabilityHour extends Model
{
    protected $fillable = [
        'outlet_id',
        'day',
        'enabled',
        'open',
        'close',
        'order_types',
    ];

    protected $casts = [
        'enabled'     => 'boolean',
        'order_types' => 'array',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
