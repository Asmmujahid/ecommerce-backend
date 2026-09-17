<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $table = 'coupons';

    protected $fillable = [
        'vendor_id',
        'admin_coupon_scope',
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_discount',
        'usage_limit',
        'used_count',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'vendor_id' => 'integer',
        'admin_coupon_scope' => 'string',
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'status' => 'boolean',
    ];

    public function vendor()
    {
        return $this->belongsTo(
            Vendor::class,
            'vendor_id'
        );
    }

    public function isAdminCoupon(): bool
    {
        return $this->vendor_id === null;
    }

    public function isVendorCoupon(): bool
    {
        return $this->vendor_id !== null;
    }

    public function isAdminOnlyCoupon(): bool
    {
        return
            $this->isAdminCoupon() &&
            $this->admin_coupon_scope === 'admin_only';
    }

    public function isAllProductsCoupon(): bool
    {
        return
            $this->isAdminCoupon() &&
            $this->admin_coupon_scope === 'all_products';
    }

    public function appliesToProduct(
        Product $product
    ): bool {

        if ($this->isVendorCoupon()) {

            return
                $product->isVendorOwned()
                &&
                (int) $product->vendor_id ===
                (int) $this->vendor_id;
        }

        if ($this->isAdminOnlyCoupon()) {
            return $product->isAdminOwned();
        }

        if ($this->isAllProductsCoupon()) {
            return true;
        }

        return false;
    }

    public function isValid(): bool
    {
        $now = now();

        if (!$this->status) {
            return false;
        }

        if (
            $this->start_date !== null &&
            $now->lt($this->start_date)
        ) {
            return false;
        }

        if (
            $this->end_date !== null &&
            $now->gt($this->end_date)
        ) {
            return false;
        }

        if (
            $this->usage_limit !== null &&
            $this->used_count >=
                $this->usage_limit
        ) {
            return false;
        }

        return true;
    }

    public function meetsMinimumOrder(
        float $orderAmount
    ): bool {
        if ($this->min_order_amount === null) {
            return true;
        }

        return $orderAmount >=
            (float) $this->min_order_amount;
    }

    public function calculateDiscount(
        float $orderAmount
    ): float {

        $orderAmount =
            max(
                0,
                (float) $orderAmount
            );

        if (!$this->isValid()) {
            return 0;
        }

        if (
            !$this->meetsMinimumOrder(
                $orderAmount
            )
        ) {
            return 0;
        }

        $discount = 0;

        if ($this->type === 'percentage') {

            $discount =
                (
                    $orderAmount *
                    (float) $this->value
                ) / 100;

            if ($this->max_discount !== null) {
                $discount =
                    min(
                        $discount,
                        (float) $this->max_discount
                    );
            }

        } elseif ($this->type === 'fixed') {

            $discount =
                (float) $this->value;
        }

        $discount =
            min(
                max(
                    0,
                    $discount
                ),
                $orderAmount
            );

        return round(
            $discount,
            2
        );
    }
}