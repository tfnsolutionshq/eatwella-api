<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CareerOpening extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'role',
        'location',
        'employment_type',
        'description',
        'requirements',
        'is_active',
        'closes_at',
        'image_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'closes_at' => 'datetime',
    ];
}

