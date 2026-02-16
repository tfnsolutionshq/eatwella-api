<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Address extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'street_address',
        'state',
        'closest_landmark',
        'postal_code',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
