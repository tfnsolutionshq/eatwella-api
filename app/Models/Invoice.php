<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'amount',
        'payment_status',
        'payment_method'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
