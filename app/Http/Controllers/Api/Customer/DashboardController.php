<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Wishlist;
use App\Models\Cart;
use App\Models\Address;
use Illuminate\Notifications\DatabaseNotification;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | Dashboard Statistics
        |--------------------------------------------------------------------------
        */

        $totalOrders = Order::where('user_id', $user->id)->count();

        $pendingOrders = Order::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $processingOrders = Order::where('user_id', $user->id)
            ->where('status', 'processing')
            ->count();

        $completedOrders = Order::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $cancelledOrders = Order::where('user_id', $user->id)
            ->where('status', 'cancelled')
            ->count();

        $wishlistCount = Wishlist::where('user_id', $user->id)->count();

        $cartItems = Cart::where('user_id', $user->id)
            ->withCount('items')
            ->first();

        $cartCount = $cartItems ? $cartItems->items_count : 0;

        $addressCount = Address::where('user_id', $user->id)->count();

        /*
        |--------------------------------------------------------------------------
        | Recent Orders
        |--------------------------------------------------------------------------
        */

        $recentOrders = Order::where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Recent Notifications
        |--------------------------------------------------------------------------
        */

      $notifications = DatabaseNotification::where('notifiable_id', $user->id)
    ->where('notifiable_type', \App\Models\User::class)
    ->latest()
    ->take(5)
    ->get();
        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Customer dashboard loaded successfully',

            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
            ],

            'statistics' => [

                'total_orders' => $totalOrders,

                'pending_orders' => $pendingOrders,

                'processing_orders' => $processingOrders,

                'completed_orders' => $completedOrders,

                'cancelled_orders' => $cancelledOrders,

                'wishlist_items' => $wishlistCount,

                'cart_items' => $cartCount,

                'addresses' => $addressCount,

            ],

            'recent_orders' => $recentOrders,

            'recent_notifications' => $notifications,

        ], 200);
    }
}