<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'brand_id',
        'vendor_id',
        'owner_type',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'price',
        'discount_price',
        'stock',
        'thumbnail',
        'status',
        'featured',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'stock' => 'integer',
        'status' => 'boolean',
        'featured' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Ownership
    |--------------------------------------------------------------------------
    |
    | owner_type tells us WHO OWNS the product.
    |
    | vendor:
    |     The vendor owns the product.
    |     Customer pays 100%.
    |     Admin receives marketplace commission.
    |
    | admin:
    |     Admin owns the product.
    |     Product is assigned to a Vendor Store for selling/handling.
    |     Vendor receives selling/service fee.
    |
    */

    public function isVendorOwned(): bool
    {
        return strtolower(trim((string) $this->owner_type)) === 'vendor';
    }

    public function isAdminOwned(): bool
    {
        return strtolower(trim((string) $this->owner_type)) === 'admin';
    }

    /*
    |--------------------------------------------------------------------------
    | Owner Type Normalization
    |--------------------------------------------------------------------------
    |
    | This prevents values such as:
    |
    | Vendor
    | VENDOR
    | Admin
    | ADMIN
    |
    | from causing accounting classification problems.
    |
    */

    public function setOwnerTypeAttribute($value): void
    {
        $value = strtolower(trim((string) $value));

        $this->attributes['owner_type'] = in_array(
            $value,
            ['vendor', 'admin'],
            true
        )
            ? $value
            : 'vendor';
    }

    public function getOwnerTypeAttribute($value): string
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
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class,
            'category_id'
        );
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(
            Brand::class,
            'brand_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Vendor / Selling Store
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | vendor_id does NOT necessarily mean product ownership.
    |
    | It represents the Vendor Store responsible for selling/handling
    | the product.
    |
    | Example:
    |
    | owner_type = admin
    | vendor_id  = 5
    |
    | This means:
    |
    | Admin owns the product.
    | Vendor Store #5 sells/handles it.
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
    | Product Images
    |--------------------------------------------------------------------------
    */

    public function images(): HasMany
    {
        return $this->hasMany(
            ProductImage::class,
            'product_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Product Variants
    |--------------------------------------------------------------------------
    */

    public function variants(): HasMany
    {
        return $this->hasMany(
            ProductVariant::class,
            'product_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Wishlists
    |--------------------------------------------------------------------------
    */

    public function wishlists(): HasMany
    {
        return $this->hasMany(
            Wishlist::class,
            'product_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */

    public function inventories(): HasMany
    {
        return $this->hasMany(
            Inventory::class,
            'product_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Cart Items
    |--------------------------------------------------------------------------
    */

    public function cartItems(): HasMany
    {
        return $this->hasMany(
            CartItem::class,
            'product_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    */

    public function reviews(): HasMany
    {
        return $this->hasMany(
            Review::class,
            'product_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeVendorOwned(Builder $query): Builder
    {
        return $query->where('owner_type', 'vendor');
    }

    public function scopeAdminOwned(Builder $query): Builder
    {
        return $query->where('owner_type', 'admin');
    }

    public function scopeForVendorStore(
        Builder $query,
        int $vendorId
    ): Builder {
        return $query->where('vendor_id', $vendorId);
    }

    /*
    |--------------------------------------------------------------------------
    | Accounting Information
    |--------------------------------------------------------------------------
    |
    | Returns the accounting classification used by the payment system.
    |
    */

    public function getAccountingTypeAttribute(): string
    {
        return $this->isAdminOwned()
            ? 'vendor_selling_fee'
            : 'marketplace_commission';
    }
}

