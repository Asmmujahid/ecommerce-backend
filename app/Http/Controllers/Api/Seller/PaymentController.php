<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(
        PaymentService $paymentService
    ) {
        $this->paymentService = $paymentService;
    }

    /*
    |--------------------------------------------------------------------------
    | GET SELLER PAYMENTS
    |--------------------------------------------------------------------------
    |
    | GET /api/seller/payments
    |
    */

    public function index(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
                'data' => null,
            ], 404);
        }

        try {
            /*
            |--------------------------------------------------------------------------
            | Seller payments
            |--------------------------------------------------------------------------
            |
            | Payment belongs to the customer/order.
            | PaymentService filters the order items by this vendor.
            |
            */

            $payments = $this->paymentService->sellerPayments(
                $vendor->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment history retrieved successfully.',
                'data' => $payments,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment history.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET SELLER PAYMENT DETAILS
    |--------------------------------------------------------------------------
    |
    | GET /api/seller/payments/{id}
    |
    | Seller can only see a payment when at least one order item belongs
    | to the seller's Vendor Store.
    |
    */

    public function show(string $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
                'data' => null,
            ], 404);
        }

        if (!ctype_digit($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment ID.',
                'data' => null,
            ], 422);
        }

        try {
            /*
            |--------------------------------------------------------------------------
            | Seller-scoped payment
            |--------------------------------------------------------------------------
            |
            | A payment belongs to the customer's order.
            |
            | The seller must NOT receive other vendors' order items.
            |
            */

            $payment = Payment::with([
                'user',
                'order',
                'order.coupon',

                /*
                |--------------------------------------------------------------------------
                | Only this seller's order items
                |--------------------------------------------------------------------------
                */

                'order.items' => function ($query) use ($vendor) {
                    $query->where(
                        'vendor_id',
                        $vendor->id
                    );
                },

                'order.items.vendor',
                'order.items.product',
                'order.items.productVariant',
                'order.items.vendorEarning',
            ])
                ->whereHas(
                    'order.items',
                    function ($query) use ($vendor) {
                        $query->where(
                            'vendor_id',
                            $vendor->id
                        );
                    }
                )
                ->find((int) $id);

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment retrieved successfully.',
                'data' => $payment,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET SELLER EARNINGS
    |--------------------------------------------------------------------------
    |
    | GET /api/seller/earnings
    |
    | IMPORTANT:
    | The controller does NOT calculate earnings.
    |
    | PaymentService is the accounting authority.
    |
    */

    public function earnings(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
                'data' => null,
            ], 404);
        }

        try {
            /*
            |--------------------------------------------------------------------------
            | Backend-calculated seller earnings
            |--------------------------------------------------------------------------
            |
            | PaymentService must return values such as:
            |
            | gross_sales
            | marketplace_commission
            | vendor_selling_fees
            | vendor_earnings
            | pending_earnings
            | paid_earnings
            | reversed_earnings
            |
            | The frontend only displays these values.
            |
            */

            $earnings = $this->paymentService->sellerEarnings(
                $vendor->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Seller earnings retrieved successfully.',
                'data' => $earnings,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve seller earnings.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

