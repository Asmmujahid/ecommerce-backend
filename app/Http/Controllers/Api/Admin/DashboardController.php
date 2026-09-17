<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Coupon;
use App\Models\Vendor;
use App\Models\Review;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Dashboard Counts
        $statistics = [

            'users' => [
                'total_users' => User::count(),
            ],

            'products' => [
                'total_products' => Product::count(),
            ],

            'categories' => [
                'total_categories' => Category::count(),
            ],

            'brands' => [
                'total_brands' => Brand::count(),
            ],

            'orders' => [
                'total_orders' => Order::count(),
            ],

            'vendors' => [
                'total_vendors' => Vendor::count(),
            ],

            'coupons' => [
                'total_coupons' => Coupon::count(),
            ],

            'reviews' => [
                'total_reviews' => Review::count(),
            ],

            'return_requests' => [
                'total_return_requests' => ReturnRequest::count(),
            ],
        ];

        // Monthly Sales
        $monthlySales = Order::select(
                DB::raw("MONTH(created_at) as month"),
                DB::raw("COUNT(*) as sales")
            )
            ->groupBy(DB::raw("MONTH(created_at)"))
            ->orderBy(DB::raw("MONTH(created_at)"))
            ->get()
            ->map(function ($item) {

                return [
                    'month' => date('M', mktime(0, 0, 0, $item->month, 1)),
                    'sales' => (int) $item->sales,
                ];
            });

        // Monthly Revenue
        $monthlyRevenue = Order::select(
                DB::raw("MONTH(created_at) as month"),
                DB::raw("SUM(total_amount) as revenue")
            )
            ->groupBy(DB::raw("MONTH(created_at)"))
            ->orderBy(DB::raw("MONTH(created_at)"))
            ->get()
            ->map(function ($item) {

                return [
                    'month' => date('M', mktime(0, 0, 0, $item->month, 1)),
                    'revenue' => (float) $item->revenue,
                ];
            });

        // Recent Orders
        $recentOrders = Order::with('user')
            ->latest()
            ->take(5)
            ->get();

        // Recent Users
        $recentUsers = User::latest()
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Admin Dashboard Statistics',

            'data' => [

                'statistics' => $statistics,

                'monthly_sales' => $monthlySales,

                'monthly_revenue' => $monthlyRevenue,

                'recent_orders' => $recentOrders,

                'recent_users' => $recentUsers,
            ]
        ], 200);
    }
}