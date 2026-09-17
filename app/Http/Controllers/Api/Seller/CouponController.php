<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET SELLER COUPONS
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found for this seller.',
                'data' => [],
            ], 404);
        }

        /*
         * Seller can ONLY see coupons belonging to
         * the authenticated vendor.
         */
        $coupons = Coupon::query()
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Coupons retrieved successfully.',
            'data' => $coupons,
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE SELLER COUPON
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found for this seller.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize coupon code
        |--------------------------------------------------------------------------
        */

        $request->merge([
            'code' => strtoupper(
                trim(
                    (string) $request->input('code', '')
                )
            ),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validate seller coupon
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | vendor_id is intentionally NOT accepted.
        |
        | admin_coupon_scope is intentionally NOT accepted.
        |
        | Both are controlled by the server.
        |
        */

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:coupons,code',
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
                'nullable',
                'boolean',
            ],
        ]);

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
        | Fixed coupon does not use max_discount.
        |
        */

        if ($validated['type'] === 'fixed') {
            $validated['max_discount'] = null;
        }

        try {
            $coupon = DB::transaction(
                function () use (
                    $validated,
                    $vendor
                ) {
                    return Coupon::create([
                        /*
                         * Server-controlled vendor.
                         */
                        'vendor_id' => $vendor->id,

                        /*
                         * Vendor coupons NEVER have
                         * an admin coupon scope.
                         */
                        'admin_coupon_scope' => null,

                        'code' => strtoupper(
                            trim(
                                $validated['code']
                            )
                        ),

                        'type' => $validated['type'],

                        'value' => $validated['value'],

                        'min_order_amount' =>
                            $validated['min_order_amount'] ?? null,

                        'max_discount' =>
                            $validated['max_discount'] ?? null,

                        'usage_limit' =>
                            $validated['usage_limit'] ?? null,

                        /*
                         * New coupon starts at zero usage.
                         */
                        'used_count' => 0,

                        'start_date' =>
                            $validated['start_date'],

                        'end_date' =>
                            $validated['end_date'],

                        'status' =>
                            $validated['status'] ?? true,
                    ]);
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'Coupon created successfully.',
                'data' => $coupon->fresh(),
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
    | SHOW SELLER COUPON
    |--------------------------------------------------------------------------
    */

    public function show(string $id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
            ], 404);
        }

        /*
         * Seller can only access its own coupon.
         */
        $coupon = Coupon::query()
            ->where('vendor_id', $vendor->id)
            ->where('id', $id)
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Coupon retrieved successfully.',
            'data' => $coupon,
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE SELLER COUPON
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        string $id
    ) {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
            ], 404);
        }

        /*
         * IMPORTANT:
         *
         * Seller can only update its own coupon.
         */
        $coupon = Coupon::query()
            ->where('vendor_id', $vendor->id)
            ->where('id', $id)
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize code
        |--------------------------------------------------------------------------
        */

        $request->merge([
            'code' => strtoupper(
                trim(
                    (string) $request->input('code', '')
                )
            ),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

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
                'nullable',
                'boolean',
            ],
        ]);

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
            /*
             * Never allow seller to change vendor ownership.
             */
            $coupon->update([
                'vendor_id' => $vendor->id,

                /*
                 * Vendor coupon = NULL admin scope.
                 */
                'admin_coupon_scope' => null,

                'code' => strtoupper(
                    trim(
                        $validated['code']
                    )
                ),

                'type' =>
                    $validated['type'],

                'value' =>
                    $validated['value'],

                'min_order_amount' =>
                    $validated['min_order_amount'] ?? null,

                'max_discount' =>
                    $validated['max_discount'] ?? null,

                'usage_limit' =>
                    $validated['usage_limit'] ?? null,

                'start_date' =>
                    $validated['start_date'],

                'end_date' =>
                    $validated['end_date'],

                'status' =>
                    $validated['status'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Coupon updated successfully.',
                'data' => $coupon->fresh(),
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
    | DELETE SELLER COUPON
    |--------------------------------------------------------------------------
    */

    public function destroy(string $id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
            ], 404);
        }

        /*
         * Seller can only delete its own coupon.
         */
        $coupon = Coupon::query()
            ->where('vendor_id', $vendor->id)
            ->where('id', $id)
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found.',
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