<?php

namespace App\Models;

use Carbon\Carbon;
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

    protected $appends = ['is_open'];

    public function getIsOpenAttribute(): bool
    {
        $tz  = Setting::where('key', 'restaurant_timezone')->value('value') ?? 'Africa/Lagos';
        $now = Carbon::now($tz);
        $day = $now->format('l');

        // Use outlet-specific hours if set
        $hours = $this->availabilityHours->firstWhere('day', $day);

        // Fall back to global hours
        if (! $hours) {
            $hoursJson = Setting::where('key', 'availability_hours')->value('value');
            $global    = $hoursJson ? json_decode($hoursJson, true) : [];
            $entry     = collect($global)->firstWhere('day', $day);
            if (! $entry) return false;
            $hours = (object) $entry;
        }

        if (! ($hours->enabled ?? false)) return false;
        if (empty($hours->open) || empty($hours->close)) return false;

        $open  = Carbon::parse($hours->open,  $tz)->setDateFrom($now);
        $close = Carbon::parse($hours->close, $tz)->setDateFrom($now);

        return $now->between($open, $close);
    }

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
        return $this->belongsToMany(User::class, 'outlet_user')->where('role', 'supervisor');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
