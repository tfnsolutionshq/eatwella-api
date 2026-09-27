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
        'paystack_reference',
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
        'free_delivery_amount',
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
        'customer_note',
        'delivery_zone_id',
        'completed_by_id',
        'sent_to_kitchen_by_id',
        'sent_to_kitchen_at',
        'completed_at',
        'outlet_id',
        'payment_account_id',
        'post_payment_action',
        'offline_id',
        'offline_created_at',
        'sync_batch_id',
        'sync_reconciliation',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'sent_to_kitchen_at' => 'datetime',
        'preparing_at' => 'datetime',
        'ready_at'     => 'datetime',
        'offline_created_at' => 'datetime',
        'tax_details' => 'array',
        'sync_reconciliation' => 'array',
        'tax_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'free_delivery_amount' => 'decimal:2',
    ];

    protected $hidden = [
        'delivery_pin',
    ];

    protected $appends = ['completion_time_minutes', 'delivery_agent_info'];

    public function getDeliveryAgentInfoAttribute(): ?array
    {
        if (! $this->delivery_agent_id || ! $this->relationLoaded('deliveryAgent') || ! $this->deliveryAgent) {
            return null;
        }

        return [
            'id'    => $this->deliveryAgent->id,
            'name'  => $this->deliveryAgent->name,
            'phone' => $this->deliveryAgent->phone,
        ];
    }

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

    public function ignoredByRiders()
    {
        return $this->belongsToMany(User::class, 'rider_ignored_orders')->withTimestamps();
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function syncBatch(): BelongsTo
    {
        return $this->belongsTo(OfflineSyncBatch::class, 'sync_batch_id');
    }

    public function deductItemsStock(): void
    {
        $this->loadMissing('orderItems');
        foreach ($this->orderItems as $item) {
            $menu = \App\Models\Menu::find($item->menu_id);
            if (! $menu) continue;

            if ($this->outlet_id) {
                $outletStock = \App\Models\OutletMenuStock::where('outlet_id', $this->outlet_id)
                    ->where('menu_id', $menu->id)
                    ->first();
                if ($outletStock) {
                    $before = $outletStock->stock_quantity;
                    $after  = max(0, $before - $item->quantity);
                    $outletStock->update(['stock_quantity' => $after]);
                    \App\Models\InventoryLog::create([
                        'menu_id'          => $menu->id,
                        'user_id'          => $this->user_id,
                        'type'             => 'deduction',
                        'quantity_before'  => $before,
                        'quantity_changed' => -$item->quantity,
                        'quantity_after'   => $after,
                        'note'             => 'Order deduction (outlet)',
                    ]);
                    continue;
                }
            }

            $menu->deductStock($item->quantity, $this->user_id);
        }
    }

    public function restoreItemsStock(): void
    {
        $this->loadMissing('orderItems');
        foreach ($this->orderItems as $item) {
            $menu = \App\Models\Menu::find($item->menu_id);
            if (! $menu) continue;

            if ($this->outlet_id) {
                $outletStock = \App\Models\OutletMenuStock::where('outlet_id', $this->outlet_id)
                    ->where('menu_id', $menu->id)
                    ->first();
                if ($outletStock) {
                    $before = $outletStock->stock_quantity;
                    $after  = $before + $item->quantity;
                    $outletStock->update(['stock_quantity' => $after]);
                    \App\Models\InventoryLog::create([
                        'menu_id'          => $menu->id,
                        'user_id'          => null,
                        'type'             => 'restock',
                        'quantity_before'  => $before,
                        'quantity_changed' => $item->quantity,
                        'quantity_after'   => $after,
                        'note'             => 'Stock restored due to cancellation #' . $this->order_number,
                    ]);
                    continue;
                }
            }

            if ($menu->stock_quantity !== null) {
                $before = $menu->stock_quantity;
                $after  = $before + $item->quantity;
                $menu->update(['stock_quantity' => $after, 'is_available' => true]);
                \App\Models\InventoryLog::create([
                    'menu_id'          => $menu->id,
                    'user_id'          => null,
                    'type'             => 'restock',
                    'quantity_before'  => $before,
                    'quantity_changed' => $item->quantity,
                    'quantity_after'   => $after,
                    'note'             => 'Stock restored due to cancellation #' . $this->order_number,
                ]);
            }
        }
    }
}
