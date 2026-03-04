<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CareerApplication extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
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
}
