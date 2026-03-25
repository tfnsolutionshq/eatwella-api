<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_number',
        'user_id',
        'cashier_id',
        'delivery_agent_id',
        'assigned_by_supervisor_id',
        'order_type',
        'payment_type',
        'customer_email',
        'customer_name',
        'customer_phone',
        'table_number',
        'delivery_address',
        'delivery_city',
        'delivery_zip',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'delivery_fee',
        'tax_details',
        'discount_code',
        'final_amount',
        'status',
        'points_earned',
        'assigned_at',
        'expires_at',
        'takeaway_amount',
        'delivery_proof_image',
        'delivery_note',
        'delivery_zone_id',
        'completed_by_id',
        'completed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'tax_details' => 'array',
        'tax_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
    ];

    protected $appends = ['completion_time_minutes'];

    public function getCompletionTimeMinutesAttribute()
    {
        if ($this->completed_at && $this->created_at) {
            return round($this->created_at->diffInMinutes($this->completed_at), 2);
        }
        return null;
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function deliveryAgent()
    {
        return $this->belongsTo(User::class, 'delivery_agent_id');
    }

    public function assignedBySupervisor()
    {
        return $this->belongsTo(User::class, 'assigned_by_supervisor_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function deliveryZone()
    {
        return $this->belongsTo(Zone::class, 'delivery_zone_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
