<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_number',
        'subtotal_amount',
        'coupon_id',
        'coupon_code',
        'discount_amount',
        'total_amount',
        'status',
        'payment_status',
        'payment_method',
        'shipping_address',
        'tracking_number',
        'admin_note',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | COUPON
    |--------------------------------------------------------------------------
    */

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(
            Coupon::class,
            'coupon_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER ITEMS
    |--------------------------------------------------------------------------
    */

    public function items(): HasMany
    {
        return $this->hasMany(
            OrderItem::class,
            'order_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT
    |--------------------------------------------------------------------------
    */

    public function payment(): HasOne
    {
        return $this->hasOne(
            Payment::class,
            'order_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VENDOR EARNINGS
    |--------------------------------------------------------------------------
    */

    public function vendorEarnings(): HasMany
    {
        return $this->hasMany(
            VendorEarning::class,
            'order_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | RETURN REQUESTS
    |--------------------------------------------------------------------------
    */

    public function returnRequests(): HasMany
    {
        return $this->hasMany(
            ReturnRequest::class,
            'order_id'
        );
    }
}

