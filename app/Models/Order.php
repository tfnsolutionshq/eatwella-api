<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_number',
        'user_id',
        'attendant_id',
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
        'delivery_pin',
        'points_earned',
        'assigned_at',
        'expires_at',
        'takeaway_amount',
        'delivery_proof_image',
        'delivery_note',
        'delivery_zone_id',
        'completed_by_id',
        'sent_to_kitchen_by_id',
        'sent_to_kitchen_at',
        'completed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'sent_to_kitchen_at' => 'datetime',
        'preparing_at' => 'datetime',
        'ready_at'     => 'datetime',
        'tax_details' => 'array',
        'tax_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
    ];

    protected $hidden = [
        'delivery_pin',
    ];

    protected $appends = ['completion_time_minutes'];

    public static function generateOrderNumber(): string
    {
        do {
            $number = 'EW' . strtoupper(substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(8))), 0, 8));
        } while (self::whereRaw('UPPER(order_number) = ?', [$number])->exists());

        return $number;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->orWhereRaw('UPPER(' . ($field ?? $this->getRouteKeyName()) . ') = ?', [strtoupper($value)])
            ->firstOrFail();
    }

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

    public function attendant()
    {
        return $this->belongsTo(User::class, 'attendant_id');
    }

    public function deliveryAgent()
    {
        return $this->belongsTo(User::class, 'delivery_agent_id');
    }

    public function assignedBySupervisor()
    {
        return $this->belongsTo(User::class, 'assigned_by_supervisor_id');
    }

    public function sentToKitchenBy()
    {
        return $this->belongsTo(User::class, 'sent_to_kitchen_by_id');
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
