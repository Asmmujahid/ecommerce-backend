<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_name',
        'store_slug',
        'description',
        'logo',
        'banner',
        'phone',
        'address',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
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
    | PRODUCTS
    |--------------------------------------------------------------------------
    */

    public function products(): HasMany
    {
        return $this->hasMany(
            Product::class,
            'vendor_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER ITEMS
    |--------------------------------------------------------------------------
    */

    public function orderItems(): HasMany
    {
        return $this->hasMany(
            OrderItem::class,
            'vendor_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VENDOR EARNINGS
    |--------------------------------------------------------------------------
    */

    public function earnings(): HasMany
    {
        return $this->hasMany(
            VendorEarning::class,
            'vendor_id'
        );
    }
}

