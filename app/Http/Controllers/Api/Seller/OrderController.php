<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
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
    | GET CURRENT VENDOR
    |--------------------------------------------------------------------------
    */

    protected function getVendor()
    {
        return auth()->user()?->vendor;
    }

    /*
    |--------------------------------------------------------------------------
    | DISPLAY SELLER ORDERS
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $vendor = $this->getVendor();

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor account not found.',
            ], 404);
        }

        $orders = Order::with([
            'user',

            /*
            |--------------------------------------------------------------------------
            | ONLY THIS VENDOR'S ORDER ITEMS
            |--------------------------------------------------------------------------
            */

            'items' => function ($query) use ($vendor) {
                $query->where(
                    'vendor_id',
                    $vendor->id
                );
            },

            'items.product',
            'items.productVariant',
            'items.vendor',
            'items.vendorEarning',
            'payment',
        ])
        ->whereHas(
            'items',
            function ($query) use ($vendor) {
                $query->where(
                    'vendor_id',
                    $vendor->id
                );
            }
        )
        ->latest()
        ->get();

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => $orders,
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | SELLER CANNOT CREATE ORDERS
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Orders are created by customers.',
        ], 403);
    }

    /*
    |--------------------------------------------------------------------------
    | DISPLAY SINGLE ORDER
    |--------------------------------------------------------------------------
    */

    public function show(string $id)
    {
        $vendor = $this->getVendor();

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor account not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | FIND ORDER ONLY IF IT CONTAINS THIS VENDOR'S ITEMS
        |--------------------------------------------------------------------------
        */

        $order = Order::with([
            'user',

            'items' => function ($query) use ($vendor) {
                $query->where(
                    'vendor_id',
                    $vendor->id
                );
            },

            'items.product',
            'items.productVariant',
            'items.vendor',
            'items.vendorEarning',
            'payment',
        ])
        ->whereHas(
            'items',
            function ($query) use ($vendor) {
                $query->where(
                    'vendor_id',
                    $vendor->id
                );
            }
        )
        ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully.',
            'data' => $order,
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ORDER STATUS
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | Vendor updates the SAME orders.status column used by Admin.
    |
    | We do NOT directly call:
    |
    |     $order->status = ...
    |     $order->save()
    |
    | Instead, we use OrderService::updateStatus().
    |
    | This keeps:
    |
    | - Admin status
    | - Vendor status
    | - Customer status
    | - Stock handling
    | - Vendor earnings handling
    | - Notifications
    |
    | synchronized.
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        string $id
    ) {
        $vendor = $this->getVendor();

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor account not found.',
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
                'in:pending,processing,shipped,delivered,cancelled',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | FIND ORDER BELONGING TO THIS VENDOR
        |--------------------------------------------------------------------------
        |
        | We use order_items.vendor_id because vendor_id on the
        | order item is the historical selling-store snapshot.
        |--------------------------------------------------------------------------
        */

        $order = Order::query()
            ->whereHas(
                'items',
                function ($query) use ($vendor) {
                    $query->where(
                        'vendor_id',
                        $vendor->id
                    );
                }
            )
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE THROUGH ORDER SERVICE
        |--------------------------------------------------------------------------
        |
        | THIS IS THE IMPORTANT FIX.
        |
        | Admin and Vendor now use the same method.
        |--------------------------------------------------------------------------
        */

        try {
            $order = $this->orderService->updateStatus(
                $order,
                $validated['status']
            );

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | RETURN COMPLETE ORDER
        |--------------------------------------------------------------------------
        |
        | The service already reloads the order.
        | We additionally make sure the response contains only
        | this vendor's order items.
        |--------------------------------------------------------------------------
        */

        $order = Order::with([
            'user',

            'items' => function ($query) use ($vendor) {
                $query->where(
                    'vendor_id',
                    $vendor->id
                );
            },

            'items.product',
            'items.productVariant',
            'items.vendor',
            'items.vendorEarning',
            'payment',
        ])->find($order->id);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.',
            'data' => $order,
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | SELLER CANNOT DELETE ORDERS
    |--------------------------------------------------------------------------
    */

    public function destroy(string $id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Orders cannot be deleted by sellers.',
        ], 403);
    }
}