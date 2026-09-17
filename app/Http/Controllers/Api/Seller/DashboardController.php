<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    /**
     * Seller Dashboard
     *
     * Important:
     * order_items.vendor_id represents the Vendor Store that sold
     * the item. Therefore seller dashboard calculations are based on
     * order_items.vendor_id instead of product ownership.
     */
    public function index()
    {
        $user = auth()->user();

        $vendor = $user->vendor;

        /*
        |--------------------------------------------------------------------------
        | Vendor check
        |--------------------------------------------------------------------------
        */

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Vendor ID
        |--------------------------------------------------------------------------
        */

        $vendorId = $vendor->id;

        /*
        |--------------------------------------------------------------------------
        | Vendor Order Items
        |--------------------------------------------------------------------------
        |
        | We use order_items.vendor_id because:
        |
        | 1. Vendor-owned product -> vendor_id = selling vendor
        | 2. Admin-owned product assigned to vendor store
        |    -> vendor_id = selling vendor
        |
        | This makes the dashboard work for both cases.
        |
        */

        $vendorItems = OrderItem::query()
            ->where('vendor_id', $vendorId)
            ->with([
                'order.user',
                'product',
            ])
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Orders belonging to this vendor
        |--------------------------------------------------------------------------
        */

        $vendorOrders = $vendorItems
            ->filter(function ($item) {
                return $item->order !== null;
            })
            ->pluck('order')
            ->unique('id')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Total Orders
        |--------------------------------------------------------------------------
        */

        $totalOrders = $vendorOrders->count();

        /*
        |--------------------------------------------------------------------------
        | Pending Orders
        |--------------------------------------------------------------------------
        */

        $pendingOrders = $vendorOrders
            ->where('status', 'pending')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Completed / Delivered Orders
        |--------------------------------------------------------------------------
        */

        $completedOrders = $vendorOrders
            ->where('status', 'delivered')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Total Sales
        |--------------------------------------------------------------------------
        |
        | Calculate the seller's sales from the vendor's order items.
        |
        | We only count completed/delivered orders here.
        |
        */

        $salesItems = $vendorItems->filter(function ($item) {
            return $item->order &&
                $item->order->status === 'delivered';
        });

        $totalSales = $salesItems->sum(function ($item) {
            return $this->getItemAmount($item);
        });

        /*
        |--------------------------------------------------------------------------
        | Monthly Sales
        |--------------------------------------------------------------------------
        |
        | Current year:
        |
        | January -> December
        |
        | Even if a month has zero sales, it is returned.
        | This allows the React chart to display all months.
        |
        */

        $monthlySales = $this->getMonthlySales(
            $salesItems,
            now()->year
        );

        /*
        |--------------------------------------------------------------------------
        | Recent Orders
        |--------------------------------------------------------------------------
        |
        | Latest 5 orders belonging to this vendor.
        |
        */

        $recentOrders = $vendorOrders
            ->sortByDesc(function ($order) {
                return $order->created_at;
            })
            ->take(5)
            ->values()
            ->map(function ($order) use ($vendorId) {

                /*
                |--------------------------------------------------------------------------
                | Only calculate the amount belonging to this vendor
                |--------------------------------------------------------------------------
                */

                $orderAmount = $order->items
                    ? $order->items
                        ->where('vendor_id', $vendorId)
                        ->sum(function ($item) {
                            return $this->getItemAmount($item);
                        })
                    : 0;

                /*
                |--------------------------------------------------------------------------
                | Payment status
                |--------------------------------------------------------------------------
                */

                $paymentStatus = null;

                if ($order->relationLoaded('payment') && $order->payment) {
                    $paymentStatus = $order->payment->status;
                }

                /*
                | Some projects have payment_status directly on orders.
                */

                if (!$paymentStatus) {
                    $paymentStatus = $order->payment_status ?? 'pending';
                }

                return [
                    'id' => $order->id,

                    'user' => $order->user
                        ? [
                            'id' => $order->user->id,
                            'name' => $order->user->name,
                        ]
                        : null,

                    'total_amount' => round(
                        (float) $orderAmount,
                        2
                    ),

                    'payment_status' => $paymentStatus,

                    'status' => $order->status,

                    'created_at' => $order->created_at,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Top Selling Products
        |--------------------------------------------------------------------------
        |
        | Count quantities sold by product for this vendor.
        |
        */

        $topProducts = $salesItems
            ->groupBy('product_id')
            ->map(function (Collection $items, $productId) {

                $firstItem = $items->first();

                $product = $firstItem?->product;

                $quantity = $items->sum(function ($item) {
                    return (int) ($item->quantity ?? 0);
                });

                return [
                    'product_id' => $productId,

                    'total_quantity' => $quantity,

                    'product' => $product
                        ? [
                            'id' => $product->id,
                            'name' => $product->name,
                        ]
                        : null,
                ];
            })
            ->sortByDesc('total_quantity')
            ->take(5)
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'data' => [
                /*
                |--------------------------------------------------------------------------
                | Seller
                |--------------------------------------------------------------------------
                */

                'seller_name' => $user->name,

                'store_name' => $vendor->store_name,

                /*
                |--------------------------------------------------------------------------
                | Summary Cards
                |--------------------------------------------------------------------------
                */

                'total_products' => $vendor->products()->count(),

                'total_orders' => $totalOrders,

                'total_sales' => round(
                    (float) $totalSales,
                    2
                ),

                'pending_orders' => $pendingOrders,

                'completed_orders' => $completedOrders,

                /*
                |--------------------------------------------------------------------------
                | Chart
                |--------------------------------------------------------------------------
                */

                'monthly_sales' => $monthlySales,

                /*
                |--------------------------------------------------------------------------
                | Recent Orders
                |--------------------------------------------------------------------------
                */

                'recent_orders' => $recentOrders,

                /*
                |--------------------------------------------------------------------------
                | Top Products
                |--------------------------------------------------------------------------
                */

                'top_products' => $topProducts,
            ],
        ]);
    }

    /**
     * Calculate order item amount safely.
     *
     * Supports common OrderItem column names:
     *
     * subtotal
     * total
     * line_total
     * amount
     *
     * If none exists, price * quantity is used.
     */
    private function getItemAmount($item): float
    {
        /*
        |--------------------------------------------------------------------------
        | Subtotal
        |--------------------------------------------------------------------------
        */

        if (
            isset($item->subtotal) &&
            $item->subtotal !== null
        ) {
            return (float) $item->subtotal;
        }

        /*
        |--------------------------------------------------------------------------
        | Total
        |--------------------------------------------------------------------------
        */

        if (
            isset($item->total) &&
            $item->total !== null
        ) {
            return (float) $item->total;
        }

        /*
        |--------------------------------------------------------------------------
        | Line Total
        |--------------------------------------------------------------------------
        */

        if (
            isset($item->line_total) &&
            $item->line_total !== null
        ) {
            return (float) $item->line_total;
        }

        /*
        |--------------------------------------------------------------------------
        | Amount
        |--------------------------------------------------------------------------
        */

        if (
            isset($item->amount) &&
            $item->amount !== null
        ) {
            return (float) $item->amount;
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback: price * quantity
        |--------------------------------------------------------------------------
        */

        $price = (float) ($item->price ?? 0);

        $quantity = (float) ($item->quantity ?? 0);

        return $price * $quantity;
    }

    /**
     * Build January -> December sales for a year.
     */
    private function getMonthlySales(
        Collection $salesItems,
        int $year
    ): array {
        $months = [];

        /*
        |--------------------------------------------------------------------------
        | Initialize all 12 months
        |--------------------------------------------------------------------------
        */

        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = [
                'month' => $month,
                'month_name' => Carbon::create(
                    $year,
                    $month,
                    1
                )->format('M'),

                'total_sales' => 0,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Add delivered sales to correct month
        |--------------------------------------------------------------------------
        */

        foreach ($salesItems as $item) {
            if (!$item->order) {
                continue;
            }

            $createdAt = $item->order->created_at;

            if (!$createdAt) {
                continue;
            }

            $date = Carbon::parse($createdAt);

            /*
            | Only current year.
            */

            if ($date->year !== $year) {
                continue;
            }

            $month = $date->month;

            $months[$month]['total_sales'] +=
                $this->getItemAmount($item);
        }

        /*
        |--------------------------------------------------------------------------
        | Format amounts
        |--------------------------------------------------------------------------
        */

        foreach ($months as $month => $data) {
            $months[$month]['total_sales'] = round(
                (float) $data['total_sales'],
                2
            );
        }

        return array_values($months);
    }
}

