<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeliveryAgentBankDetail extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_name',
        'account_number',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
