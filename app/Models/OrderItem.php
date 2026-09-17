<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',

        /*
         * Historical selling Vendor Store.
         */
        'vendor_id',

        /*
         * Historical product ownership at purchase time.
         *
         * vendor = vendor-owned product
         * admin  = admin-owned product
         */
        'product_owner_type',

        'product_variant_id',
        'quantity',
        'price',
        'total',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'quantity' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Ownership Snapshot Normalization
    |--------------------------------------------------------------------------
    |
    | The OrderItem stores the ownership type that existed when the
    | customer purchased the product.
    |
    | This is important because Product.owner_type can change later.
    |
    */

    public function setProductOwnerTypeAttribute($value): void
    {
        $value = strtolower(trim((string) $value));

        $this->attributes['product_owner_type'] = in_array(
            $value,
            ['vendor', 'admin'],
            true
        )
            ? $value
            : 'vendor';
    }

    public function getProductOwnerTypeAttribute($value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array(
            $value,
            ['vendor', 'admin'],
            true
        )
            ? $value
            : 'vendor';
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER
    |--------------------------------------------------------------------------
    */

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class,
            'order_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT
    |--------------------------------------------------------------------------
    */

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
            'product_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VENDOR / SELLING STORE
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | This is the vendor who sold/handled the item.
    |
    | It is NOT necessarily the product owner.
    |
    */

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(
            Vendor::class,
            'vendor_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT VARIANT
    |--------------------------------------------------------------------------
    */

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
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
            'order_item_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VENDOR EARNING
    |--------------------------------------------------------------------------
    |
    | One order item should have one VendorEarning record.
    |
    */

    public function vendorEarning(): HasOne
    {
        return $this->hasOne(
            VendorEarning::class,
            'order_item_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | OWNERSHIP HELPERS
    |--------------------------------------------------------------------------
    */

    public function isVendorOwned(): bool
    {
        return $this->product_owner_type === 'vendor';
    }

    public function isAdminOwned(): bool
    {
        return $this->product_owner_type === 'admin';
    }

    /*
    |--------------------------------------------------------------------------
    | Accounting Type
    |--------------------------------------------------------------------------
    */

    public function getAccountingTypeAttribute(): string
    {
        return $this->isAdminOwned()
            ? 'vendor_selling_fee'
            : 'marketplace_commission';
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeVendorOwned(Builder $query): Builder
    {
        return $query->where(
            'product_owner_type',
            'vendor'
        );
    }

    public function scopeAdminOwned(Builder $query): Builder
    {
        return $query->where(
            'product_owner_type',
            'admin'
        );
    }

    public function scopeForVendorStore(
        Builder $query,
        int $vendorId
    ): Builder {
        return $query->where(
            'vendor_id',
            $vendorId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Subtotal Helper
    |--------------------------------------------------------------------------
    */

    public function getSubtotalAttribute(): float
    {
        return round(
            (float) $this->price * (int) $this->quantity,
            2
        );
    }
}

