<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',

        then: function () {

            // Admin routes
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/admin.php'));

            // Seller routes
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/seller.php'));

            // Customer routes
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/customer.php'));

        }
    )

    ->withMiddleware(function (Middleware $middleware): void {

        // Spatie Permission Middleware Aliases
        $middleware->alias([
    'role' => RoleMiddleware::class,
    'permission' => PermissionMiddleware::class,
    'role_or_permission' => RoleOrPermissionMiddleware::class,

    'seller.approved' => \App\Http\Middleware\EnsureSellerApproved::class,
]);

    })

    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $e,
            \Illuminate\Http\Request $request
        ) {

            if ($request->is('api/*')) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);

            }

        });

    })

    ->create();