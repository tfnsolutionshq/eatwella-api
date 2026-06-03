<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RiderApplication extends Model
{
    use HasUuids;

    protected $fillable = [
        'application_id', 'first_name', 'last_name', 'email', 'phone',
        'state', 'vehicle_type', 'status', 'admin_note',
        'activation_code', 'user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            do {
                $id = 'EW' . strtoupper(substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(6))), 0, 8));
            } while (self::where('application_id', $id)->exists());

            $model->application_id = $id;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
