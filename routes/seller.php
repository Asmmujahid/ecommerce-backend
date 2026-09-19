<?php

use Illuminate\Support\Facades\Route;

// =====================================================
// SELLER CONTROLLERS
// =====================================================

use App\Http\Controllers\Api\Seller\DashboardController;
use App\Http\Controllers\Api\Seller\ProductController;
use App\Http\Controllers\Api\Seller\ProductImageController;
use App\Http\Controllers\Api\Seller\ProductVariantController;
use App\Http\Controllers\Api\Seller\InventoryController;
use App\Http\Controllers\Api\Seller\OrderController;
use App\Http\Controllers\Api\Seller\ReviewController;
use App\Http\Controllers\Api\Seller\ProfileController;
use App\Http\Controllers\Api\Seller\PaymentController;
use App\Http\Controllers\Api\Seller\ReturnRequestController;
use App\Http\Controllers\Api\Seller\CouponController;
use App\Http\Controllers\Api\Seller\AnalyticsController;
use App\Http\Controllers\Api\Seller\CustomerController;
use App\Http\Controllers\Api\Seller\StoreController;
use App\Http\Controllers\Api\Seller\BrandController;
use App\Http\Controllers\Api\Seller\CategoryController;


/*
|--------------------------------------------------------------------------
| SELLER ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('seller')
    ->middleware([
        'auth:sanctum',
        'role:seller',
        'seller.approved',
    ])
    ->name('seller.')
    ->group(function () {

        // =================================================
        // DASHBOARD
        // =================================================

        Route::get(
            '/dashboard',
            [DashboardController::class, 'index']
        );


        // =================================================
        // PRODUCTS
        // =================================================

        Route::apiResource(
            'products',
            ProductController::class
        );


        // =================================================
        // PRODUCT IMAGES
        // =================================================

        Route::get(
            'products/{product}/images',
            [ProductImageController::class, 'index']
        );

        Route::post(
            'products/{product}/images',
            [ProductImageController::class, 'store']
        );

        Route::get(
            'product-images/{image}',
            [ProductImageController::class, 'show']
        );

        Route::put(
            'product-images/{image}',
            [ProductImageController::class, 'update']
        );

        Route::delete(
            'products/{product}/images/{image}',
            [ProductImageController::class, 'destroy']
        );


        // =================================================
        // PRODUCT VARIANTS
        // =================================================

        Route::get(
            'products/{product}/variants',
            [ProductVariantController::class, 'index']
        );

        Route::post(
            'products/{product}/variants',
            [ProductVariantController::class, 'store']
        );

        Route::get(
            'product-variants/{variant}',
            [ProductVariantController::class, 'show']
        );

        Route::put(
            'product-variants/{variant}',
            [ProductVariantController::class, 'update']
        );

        Route::delete(
            'product-variants/{variant}',
            [ProductVariantController::class, 'destroy']
        );


        // =================================================
        // INVENTORY
        // =================================================

        Route::apiResource(
            'inventories',
            InventoryController::class
        );


        // =================================================
        // ORDERS
        // =================================================

        Route::apiResource(
            'orders',
            OrderController::class
        );


        // =================================================
        // REVIEWS
        // =================================================

        Route::apiResource(
            'reviews',
            ReviewController::class
        );


        // =================================================
        // PROFILE
        // =================================================

        Route::get(
            'profile',
            [ProfileController::class, 'show']
        );

        Route::put(
            'profile',
            [ProfileController::class, 'update']
        );

        Route::post(
            'change-password',
            [ProfileController::class, 'changePassword']
        );


        // =================================================
        // PAYMENTS
        // =================================================

        Route::get(
            'payments',
            [PaymentController::class, 'index']
        );

        Route::get(
            'payments/{id}',
            [PaymentController::class, 'show']
        );

        Route::get(
            'earnings',
            [PaymentController::class, 'earnings']
        );


        // =================================================
        // RETURN REQUESTS
        // =================================================

        Route::get(
            'return-requests',
            [ReturnRequestController::class, 'index']
        );

        Route::get(
            'return-requests/{id}',
            [ReturnRequestController::class, 'show']
        );

        Route::put(
            'return-requests/{id}',
            [ReturnRequestController::class, 'update']
        );


        // =================================================
        // COUPONS
        // =================================================

        Route::apiResource(
            'coupons',
            CouponController::class
        );


        // =================================================
        // ANALYTICS
        // =================================================

        Route::get(
            'analytics',
            [AnalyticsController::class, 'index']
        );


        // =================================================
        // CUSTOMERS
        // =================================================

        Route::get(
            'customers',
            [CustomerController::class, 'index']
        );

        Route::get(
            'customers/{id}',
            [CustomerController::class, 'show']
        );


        // =================================================
        // STORE
        // =================================================

        Route::get(
            'store',
            [StoreController::class, 'show']
        );

        Route::put(
            'store',
            [StoreController::class, 'update']
        );


        // =================================================
        // CATEGORIES
        // =================================================

        Route::get(
            'categories',
            [CategoryController::class, 'index']
        );

        Route::get(
            'categories/{id}',
            [CategoryController::class, 'show']
        );


        // =================================================
        // BRANDS
        // =================================================

        Route::get(
            'brands',
            [BrandController::class, 'index']
        );

        Route::get(
            'brands/{id}',
            [BrandController::class, 'show']
        );
    });