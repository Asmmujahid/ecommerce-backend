<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Seller Analytics Dashboard
     */
    public function index()
    {
        $vendor = auth()->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found'
            ], 404);
        }

        $productIds = $vendor->products()->pluck('id');

        /*
        |--------------------------------------------------------------------------
        | Total Sales
        |--------------------------------------------------------------------------
        */
        $totalSales = OrderItem::whereIn('product_id', $productIds)
            ->whereHas('order', function ($query) {
                $query->where('payment_status', 'paid');
            })
            ->sum('total');

        /*
        |--------------------------------------------------------------------------
        | Total Orders
        |--------------------------------------------------------------------------
        */
        $totalOrders = Order::whereHas('items', function ($query) use ($productIds) {
            $query->whereIn('product_id', $productIds);
        })->count();

        /*
        |--------------------------------------------------------------------------
        | Total Products
        |--------------------------------------------------------------------------
        */
        $totalProducts = $vendor->products()->count();

        /*
        |--------------------------------------------------------------------------
        | Top Products
        |--------------------------------------------------------------------------
        */
        $topProducts = OrderItem::select(
                'product_id',
                DB::raw('SUM(quantity) as total_quantity')
            )
            ->with('product:id,name')
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Monthly Sales
        |--------------------------------------------------------------------------
        */
        $monthlySales = OrderItem::select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total) as total_sales')
            )
            ->whereIn('product_id', $productIds)
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'total_products' => $totalProducts,
                'top_products' => $topProducts,
                'monthly_sales' => $monthlySales,
            ]
        ]);
    }
}