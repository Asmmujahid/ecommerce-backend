<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;

    /*
    |--------------------------------------------------------------------------
    | Spatie Permission Guard
    |--------------------------------------------------------------------------
    */

    protected $guard_name = 'web';

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'status',
    ];

    /*
    |--------------------------------------------------------------------------
    | Hidden Attributes
    |--------------------------------------------------------------------------
    */

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | Attribute Casting
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Wishlists
    |--------------------------------------------------------------------------
    */

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Addresses
    |--------------------------------------------------------------------------
    */

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    */

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Vendor
    |--------------------------------------------------------------------------
    */

    public function vendor(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Return Requests
    |--------------------------------------------------------------------------
    */

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    */

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Laravel Notifications
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | The Notifiable trait already provides:
    |
    |     notifications()
    |     unreadNotifications()
    |     readNotifications()
    |     routeNotificationFor()
    |
    | Do NOT create another notifications() relationship here.
    |
    */
}

