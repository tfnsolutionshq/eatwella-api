<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Discount extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'type',
        'value',
        'start_date',
        'end_date',
        'is_indefinite',
        'is_active'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_indefinite' => 'boolean',
        'is_active' => 'boolean',
    ];
}
