<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfflineSyncBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'outlet_id',
        'status',
        'total_orders',
        'synced_count',
        'reconciled_count',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'total_orders' => 'integer',
        'synced_count' => 'integer',
        'reconciled_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'sync_batch_id');
    }
}
