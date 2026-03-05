<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CareerApplication extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'career_opening_id',
        'full_name',
        'email',
        'phone',
        'role',
        'cv_path',
        'cover_letter_path',
        'status',
        'ip_address',
        'user_agent',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function opening()
    {
        return $this->belongsTo(CareerOpening::class, 'career_opening_id');
    }
}
