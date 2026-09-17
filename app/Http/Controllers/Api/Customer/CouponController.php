<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\CouponService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    protected CouponService $couponService;

    public function __construct(
        CouponService $couponService
    ) {
        $this->couponService = $couponService;
    }

    /*
    |--------------------------------------------------------------------------
    | GET CUSTOMER COUPONS
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'product_ids.*' => [
                'required',
                'integer',
                'distinct',
                'min:1',
                'exists:products,id',
            ],
        ]);

        $productIds = collect(
            $validated['product_ids']
        )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        $coupons = $this->couponService
            ->customerCoupons($productIds);

        return response()->json([
            'success' => true,

            'message' =>
                'Available coupons retrieved successfully.',

            'data' => $coupons->values(),
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE COUPON
    |--------------------------------------------------------------------------
    */

    public function validateCoupon(
        Request $request
    ) {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
            ],

            'product_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'product_ids.*' => [
                'required',
                'integer',
                'distinct',
                'min:1',
                'exists:products,id',
            ],
        ]);

        $result = $this->couponService
            ->validateCouponForProducts(
                $validated['code'],
                $validated['product_ids']
            );

        if (!$result['success']) {
            return response()->json(
                $result,
                400
            );
        }

        return response()->json(
            $result,
            200
        );
    }

    /*
    |--------------------------------------------------------------------------
    | APPLY COUPON
    |--------------------------------------------------------------------------
    */

    public function applyCoupon(
        Request $request
    ) {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
            ],

            'product_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'product_ids.*' => [
                'required',
                'integer',
                'distinct',
                'min:1',
                'exists:products,id',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'min:1',
                'exists:products,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | NORMALIZE PRODUCT IDS
        |--------------------------------------------------------------------------
        */

        $productIds = collect(
            $validated['product_ids']
        )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | ITEM PRODUCT IDS
        |--------------------------------------------------------------------------
        */

        $itemProductIds = collect(
            $validated['items']
        )
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | CART IDS MUST MATCH
        |--------------------------------------------------------------------------
        */

        if ($productIds !== $itemProductIds) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Product IDs do not match the cart items.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | FIND COUPON
        |--------------------------------------------------------------------------
        */

        $coupon = $this->couponService
            ->findByCode(
                $validated['code']
            );

        if (!$coupon) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Coupon not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK VALIDITY
        |--------------------------------------------------------------------------
        */

        if (!$coupon->isValid()) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Coupon is invalid, inactive, expired, or its usage limit has been reached.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | CALCULATE ELIGIBLE SUBTOTAL
        |--------------------------------------------------------------------------
        */

        $eligible = $this->couponService
            ->calculateEligibleSubtotal(
                $coupon,
                $validated['items']
            );

        if (!$eligible['success']) {
            return response()->json(
                $eligible,
                400
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CALCULATE ONE COUPON
        |--------------------------------------------------------------------------
        */

        $result = $this->couponService
            ->applyCoupon(
                $coupon,
                $eligible['eligible_subtotal']
            );

        if (!$result['success']) {
            return response()->json(
                $result,
                400
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ELIGIBLE PRODUCTS
        |--------------------------------------------------------------------------
        */

        $result['data']['eligible_product_ids'] =
            $eligible['eligible_product_ids'];

        return response()->json(
            $result,
            200
        );
    }
}