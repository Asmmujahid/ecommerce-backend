<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /*
    |--------------------------------------------------------------------------
    | ADMIN COUPONS
    |--------------------------------------------------------------------------
    */

    public function adminCoupons()
    {
        return Coupon::query()
            ->with('vendor')
            ->latest()
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER AVAILABLE COUPONS
    |--------------------------------------------------------------------------
    */

    public function customerCoupons(array $productIds = [])
    {
        $productIds = $this->normalizeIds($productIds);

        if (empty($productIds)) {
            return collect();
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get();

        if ($products->isEmpty()) {
            return collect();
        }

        $coupons = Coupon::query()
            ->with('vendor')
            ->where('status', true)

            ->where(function ($query) {
                $query
                    ->whereNull('usage_limit')
                    ->orWhereColumn(
                        'used_count',
                        '<',
                        'usage_limit'
                    );
            })

            ->where(function ($query) {
                $query
                    ->whereNull('start_date')
                    ->orWhereDate(
                        'start_date',
                        '<=',
                        now()
                    );
            })

            ->where(function ($query) {
                $query
                    ->whereNull('end_date')
                    ->orWhereDate(
                        'end_date',
                        '>=',
                        now()
                    );
            })

            ->latest()
            ->get();

        return $coupons
            ->filter(function (Coupon $coupon) use ($products) {

                return $products->contains(
                    function (Product $product) use ($coupon) {

                        return $coupon->appliesToProduct(
                            $product
                        );
                    }
                );
            })
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | FIND COUPON
    |--------------------------------------------------------------------------
    */

    public function findCoupon(int $id): ?Coupon
    {
        return Coupon::find($id);
    }

    /*
    |--------------------------------------------------------------------------
    | FIND BY CODE
    |--------------------------------------------------------------------------
    */

    public function findByCode(string $code): ?Coupon
    {
        return Coupon::query()
            ->where(
                'code',
                strtoupper(trim($code))
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE IDS
    |--------------------------------------------------------------------------
    */

    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK PRODUCT ELIGIBILITY
    |--------------------------------------------------------------------------
    */

    public function isApplicableToProducts(
        Coupon $coupon,
        Collection $products
    ): array {
        if ($products->isEmpty()) {
            return [
                'success' => false,
                'message' =>
                    'There are no products in the cart.',
            ];
        }

        $eligibleProducts = $products->filter(
            function (Product $product) use ($coupon) {

                return $coupon->appliesToProduct(
                    $product
                );
            }
        );

        if ($eligibleProducts->isEmpty()) {
            return [
                'success' => false,
                'message' =>
                    'This coupon is not applicable to the products in your cart.',
            ];
        }

        return [
            'success' => true,

            'message' =>
                'Coupon is applicable to the cart.',

            'products' =>
                $eligibleProducts->values(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE COUPON
    |--------------------------------------------------------------------------
    */

    public function createCoupon(array $data): Coupon
    {
        return DB::transaction(function () use ($data) {

            $vendorId =
                $data['vendor_id'] ?? null;

            $adminCouponScope = null;

            if ($vendorId === null) {
                $adminCouponScope =
                    $data['admin_coupon_scope'] ?? null;
            }

            return Coupon::create([
                'vendor_id' =>
                    $vendorId,

                'admin_coupon_scope' =>
                    $adminCouponScope,

                'code' =>
                    strtoupper(
                        trim($data['code'])
                    ),

                'type' =>
                    $data['type'],

                'value' =>
                    $data['value'],

                'min_order_amount' =>
                    $data['min_order_amount'] ?? null,

                'max_discount' =>
                    $data['max_discount'] ?? null,

                'usage_limit' =>
                    $data['usage_limit'] ?? null,

                'used_count' =>
                    0,

                'start_date' =>
                    $data['start_date'] ?? null,

                'end_date' =>
                    $data['end_date'] ?? null,

                'status' =>
                    $data['status'] ?? true,
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE COUPON
    |--------------------------------------------------------------------------
    */

    public function updateCoupon(
        Coupon $coupon,
        array $data
    ): Coupon {
        $coupon->update([
            'code' =>
                strtoupper(
                    trim($data['code'])
                ),

            'type' =>
                $data['type'],

            'value' =>
                $data['value'],

            'min_order_amount' =>
                $data['min_order_amount'] ?? null,

            'max_discount' =>
                $data['max_discount'] ?? null,

            'usage_limit' =>
                $data['usage_limit'] ?? null,

            'start_date' =>
                $data['start_date'] ?? null,

            'end_date' =>
                $data['end_date'] ?? null,

            'status' =>
                $data['status'] ?? true,

            'admin_coupon_scope' =>
                $coupon->isAdminCoupon()
                    ? ($data['admin_coupon_scope'] ?? null)
                    : null,
        ]);

        return $coupon->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE COUPON
    |--------------------------------------------------------------------------
    */

    public function deleteCoupon(
        Coupon $coupon
    ): bool {
        return $coupon->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE COUPON
    |--------------------------------------------------------------------------
    */

    public function validateCouponForProducts(
        string $code,
        array $productIds
    ): array {
        $coupon =
            $this->findByCode($code);

        if (!$coupon) {
            return [
                'success' => false,
                'message' =>
                    'Coupon not found.',
            ];
        }

        if (!$coupon->isValid()) {
            return [
                'success' => false,
                'message' =>
                    'Coupon is invalid or expired.',
            ];
        }

        $productIds =
            $this->normalizeIds($productIds);

        if (empty($productIds)) {
            return [
                'success' => false,
                'message' =>
                    'No products were provided.',
            ];
        }

        $products =
            Product::query()
                ->whereIn(
                    'id',
                    $productIds
                )
                ->get();

        if (
            $products->count() !==
            count($productIds)
        ) {
            return [
                'success' => false,
                'message' =>
                    'One or more products could not be found.',
            ];
        }

        $check =
            $this->isApplicableToProducts(
                $coupon,
                $products
            );

        if (!$check['success']) {
            return $check;
        }

        return [
            'success' => true,

            'message' =>
                'Coupon is valid.',

            'data' => [
                'coupon' =>
                    $coupon,

                'eligible_product_ids' =>
                    $check['products']
                        ->pluck('id')
                        ->values()
                        ->toArray(),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CALCULATE ELIGIBLE SUBTOTAL
    |--------------------------------------------------------------------------
    */

    public function calculateEligibleSubtotal(
        Coupon $coupon,
        array $items
    ): array {
        if (empty($items)) {
            return [
                'success' => false,
                'message' =>
                    'Cart items are required.',
            ];
        }

        $normalizedItems =
            collect($items)
                ->map(function ($item) {

                    return [
                        'product_id' =>
                            (int) (
                                $item['product_id']
                                ?? 0
                            ),

                        'quantity' =>
                            max(
                                1,
                                (int) (
                                    $item['quantity']
                                    ?? 1
                                )
                            ),
                    ];
                })
                ->filter(
                    fn ($item) =>
                        $item['product_id'] > 0
                )
                ->values();

        if ($normalizedItems->isEmpty()) {
            return [
                'success' => false,
                'message' =>
                    'No valid cart items were provided.',
            ];
        }

        $productIds =
            $normalizedItems
                ->pluck('product_id')
                ->unique()
                ->values()
                ->toArray();

        $products =
            Product::query()
                ->whereIn(
                    'id',
                    $productIds
                )
                ->get()
                ->keyBy('id');

        $eligibleSubtotal = 0;

        $eligibleProductIds = [];

        foreach ($normalizedItems as $item) {

            $product =
                $products->get(
                    $item['product_id']
                );

            if (!$product) {
                return [
                    'success' => false,
                    'message' =>
                        'One or more cart products could not be found.',
                ];
            }

            if (
                !$coupon->appliesToProduct(
                    $product
                )
            ) {
                continue;
            }

            $price =
                $product->discount_price !== null
                    ? (float) $product->discount_price
                    : (float) $product->price;

            $lineTotal =
                $price *
                $item['quantity'];

            $eligibleSubtotal +=
                $lineTotal;

            $eligibleProductIds[] =
                $product->id;
        }

        $eligibleSubtotal =
            round(
                $eligibleSubtotal,
                2
            );

        if ($eligibleSubtotal <= 0) {
            return [
                'success' => false,
                'message' =>
                    'This coupon is not applicable to the products in your cart.',
            ];
        }

        return [
            'success' => true,

            'eligible_subtotal' =>
                $eligibleSubtotal,

            'eligible_product_ids' =>
                array_values(
                    array_unique(
                        $eligibleProductIds
                    )
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | APPLY ONE COUPON
    |--------------------------------------------------------------------------
    */

    public function applyCoupon(
        Coupon $coupon,
        float $eligibleSubtotal
    ): array {
        $eligibleSubtotal =
            round(
                max(
                    0,
                    $eligibleSubtotal
                ),
                2
            );

        if (!$coupon->isValid()) {
            return [
                'success' => false,
                'message' =>
                    'Coupon is invalid or expired.',
            ];
        }

        if (
            !$coupon->meetsMinimumOrder(
                $eligibleSubtotal
            )
        ) {
            return [
                'success' => false,

                'message' =>
                    'Minimum eligible order amount of Rs. ' .
                    number_format(
                        (float)
                            $coupon->min_order_amount,
                        2
                    ) .
                    ' is required.',
            ];
        }

        $discount =
            $coupon->calculateDiscount(
                $eligibleSubtotal
            );

        $finalEligibleAmount =
            round(
                max(
                    0,
                    $eligibleSubtotal -
                        $discount
                ),
                2
            );

        return [
            'success' => true,

            'message' =>
                'Coupon applied successfully.',

            'data' => [
                'coupon_id' =>
                    $coupon->id,

                'coupon_code' =>
                    $coupon->code,

                'coupon_type' =>
                    $coupon->type,

                'coupon_value' =>
                    (float) $coupon->value,

                'coupon_vendor_id' =>
                    $coupon->vendor_id,

                'admin_coupon_scope' =>
                    $coupon->admin_coupon_scope,

                'eligible_subtotal' =>
                    $eligibleSubtotal,

                'discount_amount' =>
                    $discount,

                'final_eligible_amount' =>
                    $finalEligibleAmount,
            ],
        ];
    }
}