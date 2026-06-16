<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Outlet extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'city_id',
        'is_active',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // Single-outlet roles: attendant, supervisor, kitchen, store_keeper
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Delivery agents assigned to this outlet (many-to-many)
    public function deliveryAgents()
    {
        return $this->belongsToMany(User::class, 'outlet_user');
    }

    public function availabilityHours()
    {
        return $this->hasMany(OutletAvailabilityHour::class);
    }

    public function menuStock()
    {
        return $this->hasMany(OutletMenuStock::class);
    }

    public function supervisors()
    {
        return $this->hasMany(User::class)->where('role', 'supervisor');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
