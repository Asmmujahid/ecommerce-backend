<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerApproved
{
    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | User must be authenticated
        |--------------------------------------------------------------------------
        */

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Seller must have vendor profile
        |--------------------------------------------------------------------------
        */

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Seller must be approved
        |--------------------------------------------------------------------------
        */

        if (!$vendor->status) {

            return response()->json([
                'success' => false,
                'message' => 'Your seller account is waiting for admin approval.',
                'approval_status' => 'pending',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Approved seller
        |--------------------------------------------------------------------------
        */

        return $next($request);
    }
}