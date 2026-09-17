<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderStatusUpdatedNotification;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | ORDER SERVICE
    |--------------------------------------------------------------------------
    */

    protected OrderService $orderService;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        OrderService $orderService
    ) {
        $this->orderService = $orderService;
    }

    /*
    |--------------------------------------------------------------------------
    | DISPLAY ALL ORDERS
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return response()->json([
            'success' => true,

            'message' =>
                'Order list retrieved successfully',

            'data' =>
                $this->orderService->adminOrders(),

        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE NEW ORDER
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' =>
                'required|exists:users,id',

            'status' =>
                'required|in:pending,processing,shipped,delivered,cancelled',

            'payment_status' =>
                'required|in:pending,paid,failed,refunded',

            'payment_method' =>
                'required|in:cod,jazzcash,easypaisa,stripe',

            'shipping_address' =>
                'required|string',

            'items' =>
                'required|array|min:1',

            'items.*.product_id' =>
                'required|exists:products,id',

            'items.*.product_variant_id' =>
                'nullable|exists:product_variants,id',

            'items.*.quantity' =>
                'required|integer|min:1',
        ]);

        try {
            $order = $this->orderService->createOrder(
                $validated
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Order created successfully',

                'data' =>
                    $order,

            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,

                'message' =>
                    $e->getMessage(),

            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DISPLAY SINGLE ORDER
    |--------------------------------------------------------------------------
    */

    public function show(string $id)
    {
        $order = $this->orderService->findOrder($id);

        if (!$order) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Order not found',

            ], 404);
        }

        return response()->json([
            'success' => true,

            'data' =>
                $order,

        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ORDER
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        string $id
    ) {
        $order = Order::with('user')->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Order not found',

            ], 404);
        }

        $validated = $request->validate([
            'status' =>
                'required|in:pending,processing,shipped,delivered,cancelled',

            'payment_status' =>
                'required|in:pending,paid,failed,refunded',
        ]);

        /*
        |--------------------------------------------------------------------------
        | SAVE OLD VALUES
        |--------------------------------------------------------------------------
        */

        $oldStatus = $order->status;

        $oldPaymentStatus = $order->payment_status;

        /*
        |--------------------------------------------------------------------------
        | UPDATE ORDER STATUS
        |--------------------------------------------------------------------------
        */

        $this->orderService->updateStatus(
            $order,
            $validated['status']
        );

        /*
        |--------------------------------------------------------------------------
        | UPDATE PAYMENT STATUS
        |--------------------------------------------------------------------------
        */

        $this->orderService->updatePaymentStatus(
            $order,
            $validated['payment_status']
        );

        /*
        |--------------------------------------------------------------------------
        | REFRESH ORDER
        |--------------------------------------------------------------------------
        */

        $order = $order->fresh([
            'user',
            'items.product',
            'items.productVariant',
            'items.vendor',
            'payment',
            'vendorEarnings',
        ]);

        /*
        |--------------------------------------------------------------------------
        | CUSTOMER NOTIFICATION
        |--------------------------------------------------------------------------
        */

        $customer = $order?->user;

        if ($customer) {

            /*
            |--------------------------------------------------------------------------
            | DELIVERED NOTIFICATION
            |--------------------------------------------------------------------------
            */

            if (
                strtolower((string) $order->status) === 'delivered'
                &&
                strtolower((string) $oldStatus) !== 'delivered'
            ) {
                $customer->notify(
                    new OrderDeliveredNotification($order)
                );
            }

            /*
            |--------------------------------------------------------------------------
            | OTHER ORDER UPDATES
            |--------------------------------------------------------------------------
            */

            elseif (
                $oldStatus !== $order->status
                ||
                $oldPaymentStatus !== $order->payment_status
            ) {
                $customer->notify(
                    new OrderStatusUpdatedNotification(
                        $order,
                        $oldStatus,
                        $oldPaymentStatus
                    )
                );
            }
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Order updated successfully',

            'data' =>
                $order,

        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE ORDER
    |--------------------------------------------------------------------------
    */

    public function destroy(string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Order not found',

            ], 404);
        }

        $this->orderService->deleteOrder($order);

        return response()->json([
            'success' => true,

            'message' =>
                'Order deleted successfully',

        ], 200);
    }
}

