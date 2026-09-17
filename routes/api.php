<?php

use Illuminate\Support\Facades\Route;

// =====================================================
// AUTH CONTROLLERS
// =====================================================

use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;

// =====================================================
// NOTIFICATION CONTROLLER
// =====================================================

use App\Http\Controllers\Api\NotificationController;

// =====================================================
// CUSTOMER PUBLIC CONTROLLERS
// =====================================================

use App\Http\Controllers\Api\Customer\BannerController;
use App\Http\Controllers\Api\Customer\CategoryController;
use App\Http\Controllers\Api\Customer\BrandController;
use App\Http\Controllers\Api\Customer\ProductController;

/*
|--------------------------------------------------------------------------
| PUBLIC API ROUTES
|--------------------------------------------------------------------------
*/

// =====================================================
// AUTHENTICATION
// =====================================================

Route::post(
    '/register',
    [RegisterController::class, 'register']
);

Route::post(
    '/login',
    [LoginController::class, 'login']
);

Route::post(
    '/forgot-password',
    [ForgotPasswordController::class, 'sendResetLink']
);

Route::post(
    '/reset-password',
    [ResetPasswordController::class, 'reset']
);


// =====================================================
// PUBLIC BANNERS
// =====================================================

Route::get(
    '/banners',
    [BannerController::class, 'index']
);


// =====================================================
// PUBLIC CATEGORIES
// =====================================================

Route::get(
    '/categories',
    [CategoryController::class, 'index']
);

Route::get(
    '/categories/{id}',
    [CategoryController::class, 'show']
);

Route::get(
    '/categories/{id}/products',
    [CategoryController::class, 'products']
);


// =====================================================
// PUBLIC BRANDS
// =====================================================

Route::get(
    '/brands',
    [BrandController::class, 'index']
);

Route::get(
    '/brands/{id}',
    [BrandController::class, 'show']
);

Route::get(
    '/brands/{id}/products',
    [BrandController::class, 'products']
);


// =====================================================
// PUBLIC PRODUCTS
// =====================================================

Route::get(
    '/products',
    [ProductController::class, 'index']
);

Route::get(
    '/products/{id}',
    [ProductController::class, 'show']
);

Route::post(
    '/products/search',
    [ProductController::class, 'search']
);

Route::post(
    '/products/filter',
    [ProductController::class, 'filter']
);


/*
|--------------------------------------------------------------------------
| AUTHENTICATED COMMON ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // =================================================
    // LOGOUT
    // =================================================

    Route::post(
        '/logout',
        [LoginController::class, 'logout']
    );


    // =================================================
    // NOTIFICATIONS
    // =================================================

    Route::prefix('notifications')->group(function () {

        Route::get(
            '/',
            [NotificationController::class, 'index']
        );

        Route::get(
            '/unread',
            [NotificationController::class, 'unread']
        );

        Route::get(
            '/unread-count',
            [NotificationController::class, 'unreadCount']
        );

        Route::patch(
            '/{id}/read',
            [NotificationController::class, 'markAsRead']
        );

        Route::patch(
            '/read-all',
            [NotificationController::class, 'markAllAsRead']
        );

        Route::delete(
            '/{id}',
            [NotificationController::class, 'destroy']
        );

        Route::delete(
            '/',
            [NotificationController::class, 'destroyAll']
        );
    });
});