<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Payment history
     *
     * GET /api/customer/payments
     */
    public function index(Request $request)
    {
        // ---------------------------------------------
        // Check authenticated user
        // ---------------------------------------------

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // ---------------------------------------------
        // Get customer payments
        // ---------------------------------------------

        $payments = Payment::with([
            'order',
        ])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Payment history retrieved successfully.',
            'data' => $payments,
        ], 200);
    }


    /**
     * Payment details
     *
     * GET /api/customer/payments/{id}
     */
    public function show(Request $request, $id)
    {
        // ---------------------------------------------
        // Check authenticated user
        // ---------------------------------------------

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // ---------------------------------------------
        // Validate payment ID
        // ---------------------------------------------

        if (!is_numeric($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment ID.',
            ], 422);
        }

        // ---------------------------------------------
        // Find payment belonging to logged-in user
        // ---------------------------------------------

        $payment = Payment::with([
            'order',
            'order.items.product',
            'order.items.productVariant',
        ])
            ->where('id', (int) $id)
            ->where('user_id', $user->id)
            ->first();

        // ---------------------------------------------
        // Payment not found
        // ---------------------------------------------

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
            ], 404);
        }

        // ---------------------------------------------
        // Success
        // ---------------------------------------------

        return response()->json([
            'success' => true,
            'message' => 'Payment details retrieved successfully.',
            'data' => $payment,
        ], 200);
    }


    /**
     * Delete payment history
     *
     * DELETE /api/customer/payments/{id}
     */
    public function destroy(Request $request, $id)
    {
        // ---------------------------------------------
        // Check authenticated user
        // ---------------------------------------------

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // ---------------------------------------------
        // Validate payment ID
        // ---------------------------------------------

        if (!is_numeric($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment ID.',
            ], 422);
        }

        // ---------------------------------------------
        // Find payment belonging to logged-in customer
        // ---------------------------------------------

        $payment = Payment::where('id', (int) $id)
            ->where('user_id', $user->id)
            ->first();

        // ---------------------------------------------
        // Payment not found
        // ---------------------------------------------

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
            ], 404);
        }

        // ---------------------------------------------
        // Delete payment
        // ---------------------------------------------

        $payment->delete();

        // ---------------------------------------------
        // Success response
        // ---------------------------------------------

        return response()->json([
            'success' => true,
            'message' => 'Payment history deleted successfully.',
            'payment_id' => (int) $id,
        ], 200);
    }
}