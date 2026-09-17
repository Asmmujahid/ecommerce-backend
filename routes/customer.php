<?php

use Illuminate\Support\Facades\Route;

// =====================================================
// CUSTOMER CONTROLLERS
// =====================================================

use App\Http\Controllers\Api\Customer\DashboardController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\Customer\CartController;
use App\Http\Controllers\Api\Customer\OrderController;
use App\Http\Controllers\Api\Customer\WishlistController;
use App\Http\Controllers\Api\Customer\AddressController;
use App\Http\Controllers\Api\Customer\ReviewController;
use App\Http\Controllers\Api\Customer\CheckoutController;
use App\Http\Controllers\Api\Customer\PaymentController;
use App\Http\Controllers\Api\Customer\ReturnRequestController;
use App\Http\Controllers\Api\Customer\CouponController;


/*
|--------------------------------------------------------------------------
| CUSTOMER ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('customer')
    ->middleware([
        'auth:sanctum',
        'role:customer',
    ])
    ->group(function () {

        // =================================================
        // DASHBOARD
        // =================================================

        Route::get(
            '/dashboard',
            [DashboardController::class, 'index']
        );


        // =================================================
        // PROFILE
        // =================================================

        Route::get(
            '/profile',
            [ProfileController::class, 'show']
        );

        Route::put(
            '/profile',
            [ProfileController::class, 'update']
        );

        Route::post(
            '/change-password',
            [ProfileController::class, 'store']
        );

        Route::delete(
            '/profile',
            [ProfileController::class, 'destroy']
        );


        // =================================================
        // CART
        // =================================================

        Route::apiResource(
            'cart',
            CartController::class
        );


        // =================================================
        // ORDERS
        // =================================================

        Route::get(
            '/orders',
            [OrderController::class, 'index']
        );

        Route::get(
            '/orders/{id}',
            [OrderController::class, 'show']
        );

        Route::put(
            '/orders/{id}',
            [OrderController::class, 'update']
        );

        Route::delete(
            '/orders/{id}',
            [OrderController::class, 'destroy']
        );


        // =================================================
        // WISHLIST
        // =================================================

        Route::apiResource(
            'wishlists',
            WishlistController::class
        );


        // =================================================
        // ADDRESSES
        // =================================================

        Route::apiResource(
            'addresses',
            AddressController::class
        );


        // =================================================
        // REVIEWS
        // =================================================

        Route::get(
            '/reviews/reviewable-products',
            [ReviewController::class, 'reviewableProducts']
        );

        Route::apiResource(
            'reviews',
            ReviewController::class
        );


        // =================================================
        // CHECKOUT
        // =================================================

        Route::post(
            '/checkout',
            [CheckoutController::class, 'store']
        );


        // =================================================
        // PAYMENTS
        // =================================================

        Route::get(
            '/payments',
            [PaymentController::class, 'index']
        );

        Route::get(
            '/payments/{id}',
            [PaymentController::class, 'show']
        );

        Route::delete(
    '/payments/{id}',
    [PaymentController::class, 'destroy']
);


        // =================================================
        // RETURN REQUESTS
        // =================================================

        Route::get(
            '/return-requests/returnable-items',
            [ReturnRequestController::class, 'returnableItems']
        );

        Route::apiResource(
            'return-requests',
            ReturnRequestController::class
        )->only([
            'index',
            'store',
            'show',
            'destroy',
        ]);


        // =================================================
        // COUPONS
        // =================================================

        Route::get(
            '/coupons',
            [CouponController::class, 'index']
        );

        Route::post(
            '/coupons/validate',
            [CouponController::class, 'validateCoupon']
        );

        Route::post(
            '/coupons/apply',
            [CouponController::class, 'applyCoupon']
        );
    });