<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Order service.
     */
    protected OrderService $orderService;

    /**
     * Constructor.
     */
    public function __construct(
        OrderService $orderService
    ) {
        $this->orderService = $orderService;
    }

    // =====================================================
    // CUSTOMER ORDERS
    // =====================================================

    /**
     * Display all orders belonging to
     * the authenticated customer.
     */
    public function index()
    {
        try {

            $orders = $this->orderService
                ->customerOrders();

            return response()->json([
                'success' => true,

                'message' =>
                    'Orders retrieved successfully',

                'data' => $orders,

            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Failed to retrieve orders.',

                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,

            ], 500);
        }
    }

    // =====================================================
    // STORE
    // =====================================================

    /**
     * Orders are created during checkout.
     *
     * This endpoint is intentionally disabled because
     * the checkout process creates the order.
     */
    public function store(Request $request)
    {
        return response()->json([
            'success' => false,

            'message' =>
                'Orders are created during checkout.',
        ], 405);
    }

    // =====================================================
    // SHOW
    // =====================================================

    /**
     * Display a single customer order.
     */
    public function show(string $id)
    {
        try {

            $order = $this->orderService
                ->findCustomerOrder((int) $id);

            if (!$order) {

                return response()->json([
                    'success' => false,

                    'message' =>
                        'Order not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,

                'message' =>
                    'Order retrieved successfully',

                'data' => $order,

            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Failed to retrieve order.',

                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,

            ], 500);
        }
    }

    // =====================================================
    // CANCEL ORDER
    // =====================================================

    /**
     * Cancel a pending customer order.
     */
    public function update(
        Request $request,
        string $id
    ) {
        try {

            // ---------------------------------------------
            // FIND CUSTOMER ORDER
            // ---------------------------------------------

            $order = Order::where(
                'user_id',
                auth()->id()
            )->find($id);

            if (!$order) {

                return response()->json([
                    'success' => false,

                    'message' =>
                        'Order not found.',
                ], 404);
            }

            // ---------------------------------------------
            // ONLY PENDING ORDERS CAN BE CANCELLED
            // ---------------------------------------------

            if ($order->status !== 'pending') {

                return response()->json([
                    'success' => false,

                    'message' =>
                        'Only pending orders can be cancelled.',
                ], 400);
            }

            // ---------------------------------------------
            // CANCEL ORDER
            // ---------------------------------------------

            $order = $this->orderService
                ->cancelOrder($order);

            return response()->json([
                'success' => true,

                'message' =>
                    'Order cancelled successfully.',

                'data' => $order,

            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,

                'message' =>
                    $e->getMessage(),

            ], 400);
        }
    }

    // =====================================================
    // DELETE
    // =====================================================

    /**
     * Delete customer order history.
     */
    public function destroy(string $id)
    {
        try {

            // ---------------------------------------------
            // FIND CUSTOMER ORDER
            // ---------------------------------------------

            $order = Order::where(
                'user_id',
                auth()->id()
            )->find($id);

            if (!$order) {

                return response()->json([
                    'success' => false,

                    'message' =>
                        'Order not found.',
                ], 404);
            }

            // ---------------------------------------------
            // DELETE ORDER
            // ---------------------------------------------

            $this->orderService
                ->deleteOrder($order);

            return response()->json([
                'success' => true,

                'message' =>
                    'Order deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Failed to delete order.',

                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,

            ], 500);
        }
    }
}

