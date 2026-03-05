<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tax extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'rate',
        'priority',
        'is_inclusive',
        'branches',
        'is_active'
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_inclusive' => 'boolean',
        'is_active' => 'boolean',
        'branches' => 'array',
        'priority' => 'integer'
    ];
}
