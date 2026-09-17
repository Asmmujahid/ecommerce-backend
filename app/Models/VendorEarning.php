<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorEarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'order_id',
        'order_item_id',
        'gross_amount',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | VENDOR
    |--------------------------------------------------------------------------
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
    | ORDER ITEM
    |--------------------------------------------------------------------------
    */

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(
            OrderItem::class,
            'order_item_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS HELPERS
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    /*
    |--------------------------------------------------------------------------
    | SETTLEMENT HELPER
    |--------------------------------------------------------------------------
    */

    public function isSettled(): bool
    {
        return $this->status === 'paid'
            && !is_null($this->paid_at);
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopePending(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            'pending'
        );
    }

    public function scopePaid(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            'paid'
        );
    }

    public function scopeReversed(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            'reversed'
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->whereIn(
            'status',
            [
                'pending',
                'paid',
            ]
        );
    }

    public function scopeForVendor(
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
    | ACCOUNTING TYPE
    |--------------------------------------------------------------------------
    |
    | Vendor-owned product:
    |     commission_amount = Admin marketplace commission
    |
    | Admin-owned product:
    |     commission_amount = Vendor selling/service fee
    |
    |--------------------------------------------------------------------------
    */

    public function getAccountingTypeAttribute(): string
    {
        $ownerType =
            $this->orderItem?->product_owner_type;

        return $ownerType === 'admin'
            ? 'vendor_selling_fee'
            : 'marketplace_commission';
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN COMMISSION CHECK
    |--------------------------------------------------------------------------
    */

    public function isMarketplaceCommission(): bool
    {
        return $this->orderItem?->product_owner_type === 'vendor';
    }

    /*
    |--------------------------------------------------------------------------
    | VENDOR SELLING FEE CHECK
    |--------------------------------------------------------------------------
    */

    public function isVendorSellingFee(): bool
    {
        return $this->orderItem?->product_owner_type === 'admin';
    }
}

