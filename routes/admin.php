<?php

use Illuminate\Support\Facades\Route;

// =====================================================
// ADMIN CONTROLLERS
// =====================================================

use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\InventoryController;
use App\Http\Controllers\Api\Admin\ReviewController;
use App\Http\Controllers\Api\Admin\ReturnRequestController;
use App\Http\Controllers\Api\Admin\BrandController;
use App\Http\Controllers\Api\Admin\BannerController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VendorController;
use App\Http\Controllers\Api\Admin\ProfileController;
use App\Http\Controllers\Api\Admin\CouponController;


/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware([
        'auth:sanctum',
        'role:admin',
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
        // CATEGORIES
        // =================================================

        Route::apiResource(
            'categories',
            CategoryController::class
        );


        // =================================================
        // BRANDS
        // =================================================

        Route::apiResource(
            'brands',
            BrandController::class
        );


        // =================================================
        // PRODUCTS
        // =================================================

        Route::apiResource(
            'products',
            ProductController::class
        );


        // =================================================
        // ORDERS
        // =================================================

        Route::apiResource(
            'orders',
            OrderController::class
        );


        // =================================================
        // PAYMENTS
        // =================================================

        /*
         * IMPORTANT
         * -------------------------------------------------
         * Specific payment routes MUST come before
         * /payments/{id}.
         *
         * Otherwise:
         *
         * /payments/earnings
         *
         * will be interpreted as:
         *
         * /payments/{id}
         *
         * where:
         *
         * id = earnings
         */

        // Admin earnings summary
        Route::get(
            '/payments/earnings',
            [PaymentController::class, 'earnings']
        );


        // Payment list
        Route::get(
            '/payments',
            [PaymentController::class, 'index']
        );


        // Single payment
        Route::get(
            '/payments/{id}',
            [PaymentController::class, 'show']
        )->whereNumber('id');


        // Update payment
        Route::put(
            '/payments/{id}',
            [PaymentController::class, 'update']
        )->whereNumber('id');


        // Delete payment
        Route::delete(
            '/payments/{id}',
            [PaymentController::class, 'destroy']
        )->whereNumber('id');


        // =================================================
        // INVENTORIES
        // =================================================

        Route::apiResource(
            'inventories',
            InventoryController::class
        );


        // =================================================
        // REVIEWS
        // =================================================

        Route::apiResource(
            'reviews',
            ReviewController::class
        );


        // =================================================
        // RETURN REQUESTS
        // =================================================

        Route::apiResource(
            'return-requests',
            ReturnRequestController::class
        );


        // =================================================
        // BANNERS
        // =================================================

        Route::apiResource(
            'banners',
            BannerController::class
        );


        // =================================================
        // COUPONS
        // =================================================

        Route::apiResource(
            'coupons',
            CouponController::class
        );


        // =================================================
        // USERS
        // =================================================

        Route::apiResource(
            'users',
            UserController::class
        );


        // =================================================
        // VENDORS
        // =================================================

        Route::apiResource(
            'vendors',
            VendorController::class
        );


        // =================================================
        // ADMIN PROFILE
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
            [ProfileController::class, 'changePassword']
        );
    });

