<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL PAYMENTS
    |--------------------------------------------------------------------------
    |
    | GET /api/admin/payments
    |
    */

    public function index(): JsonResponse
    {
        try {
            $payments = $this->paymentService->allPayments();

            return response()->json([
                'success' => true,
                'message' => 'Payments retrieved successfully.',
                'data' => $payments,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payments.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET ADMIN EARNINGS
    |--------------------------------------------------------------------------
    |
    | GET /api/admin/payments/earnings
    |
    */

    public function earnings(): JsonResponse
    {
        try {
            $earnings = $this->paymentService->adminEarnings();

            return response()->json([
                'success' => true,
                'message' => 'Admin earnings retrieved successfully.',
                'earnings' => $earnings,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve admin earnings.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET SINGLE PAYMENT
    |--------------------------------------------------------------------------
    |
    | GET /api/admin/payments/{id}
    |
    */

    public function show(string $id): JsonResponse
    {
        try {
            if (!ctype_digit($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment ID.',
                    'data' => null,
                ], 422);
            }

            $payment = $this->paymentService->findPayment((int) $id);

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
    | UPDATE PAYMENT
    |--------------------------------------------------------------------------
    |
    | PUT /api/admin/payments/{id}
    |
    */

    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            /*
            |--------------------------------------------------------------------------
            | VALIDATE ID
            |--------------------------------------------------------------------------
            */

            if (!ctype_digit($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment ID.',
                    'data' => null,
                ], 422);
            }

            $paymentId = (int) $id;

            /*
            |--------------------------------------------------------------------------
            | FIND PAYMENT
            |--------------------------------------------------------------------------
            */

            $payment = $this->paymentService->findPayment($paymentId);

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found.',
                    'data' => null,
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([
                'status' => [
                    'required',
                    'string',
                    'in:pending,paid,failed,refunded',
                ],

                'transaction_id' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('payments', 'transaction_id')
                        ->ignore($paymentId),
                ],

                'payment_method' => [
                    'nullable',
                    'string',
                    'in:cod,bank_transfer,card,online,wallet',
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | NORMALIZE INPUT
            |--------------------------------------------------------------------------
            */

            $status = strtolower(
                trim(
                    (string) $validated['status']
                )
            );

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT:
            |
            | Convert empty transaction ID to NULL.
            |
            | Example:
            |
            | "TXN-1001" -> "TXN-1001"
            | ""         -> NULL
            |
            |--------------------------------------------------------------------------
            */

            $transactionId = null;

            if (
                array_key_exists(
                    'transaction_id',
                    $validated
                )
            ) {
                $transactionId =
                    trim(
                        (string) $validated['transaction_id']
                    );

                if ($transactionId === '') {
                    $transactionId = null;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | PAYMENT METHOD
            |--------------------------------------------------------------------------
            */

            $paymentMethod = null;

            if (
                array_key_exists(
                    'payment_method',
                    $validated
                )
            ) {
                $paymentMethod =
                    trim(
                        (string) $validated['payment_method']
                    );

                if ($paymentMethod === '') {
                    $paymentMethod = null;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE THROUGH PAYMENT SERVICE
            |--------------------------------------------------------------------------
            |
            | PaymentService is responsible for:
            |
            | - status
            | - transaction_id
            | - payment_method
            | - paid_at
            | - order payment status
            | - vendor earnings
            |
            |--------------------------------------------------------------------------
            */

            $payment =
                $this->paymentService->updatePayment(
                    $payment,
                    $status,
                    $transactionId,
                    $paymentMethod
                );

            /*
            |--------------------------------------------------------------------------
            | RETURN FRESH PAYMENT
            |--------------------------------------------------------------------------
            */

            $payment =
                $this->paymentService->findPayment(
                    $payment->id
                );

            /*
            |--------------------------------------------------------------------------
            | RESPONSE MESSAGE
            |--------------------------------------------------------------------------
            */

            $message = match ($status) {
                'paid' =>
                    'Payment marked as paid successfully.',

                'failed' =>
                    'Payment marked as failed successfully.',

                'refunded' =>
                    'Payment refunded successfully.',

                'pending' =>
                    'Payment updated successfully.',

                default =>
                    'Payment updated successfully.',
            };

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $payment,
            ], 200);

        } catch (ValidationException $e) {
            throw $e;

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE PAYMENT
    |--------------------------------------------------------------------------
    |
    | DELETE /api/admin/payments/{id}
    |
    */

    public function destroy(string $id): JsonResponse
    {
        try {
            if (!ctype_digit($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment ID.',
                ], 422);
            }

            $payment = Payment::find((int) $id);

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | PAID PAYMENTS CANNOT BE DELETED
            |--------------------------------------------------------------------------
            */

            if (
                strtolower(
                    trim(
                        (string) $payment->status
                    )
                ) === 'paid'
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Paid payments cannot be deleted because they are linked to accounting records.',
                ], 422);
            }

            $payment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
