<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'outlet_id',
        'phone',
        'birthday',
        'birth_month',
        'loyalty_points',
        'is_suspended',
        'is_activated',
        'push_notifications_enabled',
        'order_emails_enabled',
        'promotional_emails_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'           => 'datetime',
            'password'                    => 'hashed',
            'birthday'                    => 'date',
            'birth_month'                 => 'integer',
            'is_suspended'                => 'boolean',
            'is_activated'                => 'boolean',
            'push_notifications_enabled'  => 'boolean',
            'order_emails_enabled'        => 'boolean',
            'promotional_emails_enabled'  => 'boolean',
        ];
    }

    public function getFirstNameAttribute(): string
    {
        return explode(' ', $this->name, 2)[0];
    }

    public function getLastNameAttribute(): string
    {
        return explode(' ', $this->name, 2)[1] ?? '';
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function bankDetails()
    {
        return $this->hasMany(\App\Models\DeliveryAgentBankDetail::class);
    }

    // Single outlet for attendant, supervisor, kitchen, store_keeper
    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    // Many outlets for delivery agents (via outlet_user pivot)
    public function outlets()
    {
        return $this->belongsToMany(Outlet::class, 'outlet_user');
    }

    // Cities a delivery agent is assigned to
    public function cities()
    {
        return $this->belongsToMany(City::class, 'delivery_agent_cities');
    }

    public function notifications()
    {
        return $this->hasMany(\App\Models\Notification::class)->latest();
    }

    public function fcmTokens()
    {
        return $this->hasMany(\App\Models\FcmToken::class);
    }

    public function shouldReceiveOrderEmails(): bool
    {
        return $this->order_emails_enabled !== false && $this->order_emails_enabled !== 0;
    }

    public function shouldReceivePromotionalEmails(): bool
    {
        return (bool) $this->promotional_emails_enabled;
    }
}
