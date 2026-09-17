<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
    | GET ALL ADMIN COUPONS
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        try {
            $coupons = $this->couponService->adminCoupons();

            return response()->json([
                'success' => true,
                'message' => 'Coupons retrieved successfully.',
                'data' => $coupons,
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve coupons.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE ADMIN COUPON
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->merge([
            'code' => strtoupper(
                trim(
                    (string) $request->input('code', '')
                )
            ),
        ]);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:coupons,code',
            ],

            /*
             * Admin coupon must explicitly define its scope.
             */
            'admin_coupon_scope' => [
                'required',
                Rule::in([
                    'admin_only',
                    'all_products',
                ]),
            ],

            'type' => [
                'required',
                Rule::in([
                    'percentage',
                    'fixed',
                ]),
            ],

            'value' => [
                'required',
                'numeric',
                'min:0',
            ],

            'min_order_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'max_discount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'usage_limit' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'status' => [
                'required',
                'boolean',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | SECURITY RULE
        |--------------------------------------------------------------------------
        |
        | Admin coupons NEVER belong to a vendor.
        |
        */

        $validated['vendor_id'] = null;

        /*
        |--------------------------------------------------------------------------
        | Percentage validation
        |--------------------------------------------------------------------------
        */

        if (
            $validated['type'] === 'percentage' &&
            (float) $validated['value'] > 100
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage discount cannot be greater than 100.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Fixed coupon
        |--------------------------------------------------------------------------
        |
        | max_discount is meaningless for fixed discounts.
        |
        */

        if ($validated['type'] === 'fixed') {
            $validated['max_discount'] = null;
        }

        try {
            $coupon = $this->couponService->createCoupon(
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Coupon created successfully.',
                'data' => $coupon,
            ], 201);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create coupon.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW ADMIN COUPON
    |--------------------------------------------------------------------------
    */

    public function show(string $id)
    {
        try {
            /*
             * Only admin coupons can be viewed from this controller.
             *
             * vendor_id NULL = admin coupon.
             */
            $coupon = Coupon::query()
                ->whereNull('vendor_id')
                ->find((int) $id);

            if (!$coupon) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin coupon not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Coupon retrieved successfully.',
                'data' => $coupon,
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve coupon.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ADMIN COUPON
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        string $id
    ) {
        /*
         * IMPORTANT:
         *
         * Admin controller can only update admin-owned coupons.
         *
         * Vendor coupons have vendor_id != NULL.
         * Therefore they cannot be modified here.
         */
        $coupon = Coupon::query()
            ->whereNull('vendor_id')
            ->find((int) $id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Admin coupon not found.',
            ], 404);
        }

        $request->merge([
            'code' => strtoupper(
                trim(
                    (string) $request->input('code', '')
                )
            ),
        ]);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(
                    'coupons',
                    'code'
                )->ignore($coupon->id),
            ],

            /*
             * Admin coupon must always have one of these scopes.
             */
            'admin_coupon_scope' => [
                'required',
                Rule::in([
                    'admin_only',
                    'all_products',
                ]),
            ],

            'type' => [
                'required',
                Rule::in([
                    'percentage',
                    'fixed',
                ]),
            ],

            'value' => [
                'required',
                'numeric',
                'min:0',
            ],

            'min_order_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'max_discount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'usage_limit' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'status' => [
                'required',
                'boolean',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | SECURITY
        |--------------------------------------------------------------------------
        |
        | Never allow vendor_id to be changed through admin coupon API.
        |
        */

        $validated['vendor_id'] = null;

        /*
        |--------------------------------------------------------------------------
        | Percentage validation
        |--------------------------------------------------------------------------
        */

        if (
            $validated['type'] === 'percentage' &&
            (float) $validated['value'] > 100
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage discount cannot be greater than 100.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Fixed coupon
        |--------------------------------------------------------------------------
        */

        if ($validated['type'] === 'fixed') {
            $validated['max_discount'] = null;
        }

        try {
            $updatedCoupon = $this->couponService->updateCoupon(
                $coupon,
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Coupon updated successfully.',
                'data' => $updatedCoupon,
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update coupon.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE ADMIN COUPON
    |--------------------------------------------------------------------------
    */

    public function destroy(string $id)
    {
        /*
         * Only delete admin coupons.
         */
        $coupon = Coupon::query()
            ->whereNull('vendor_id')
            ->find((int) $id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Admin coupon not found.',
            ], 404);
        }

        try {
            $coupon->delete();

            return response()->json([
                'success' => true,
                'message' => 'Coupon deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete coupon.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }
}