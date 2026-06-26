<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAccount extends Model
{
    protected $fillable = [
        'type',
        'label',
        'account_name',
        'account_number',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];
}
