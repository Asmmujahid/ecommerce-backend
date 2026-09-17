<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Find User
        |--------------------------------------------------------------------------
        */

        $user = User::with('roles')
            ->where('email', $validated['email'])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Check Credentials
        |--------------------------------------------------------------------------
        */

        if (
            !$user ||
            !Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Check General User Status
        |--------------------------------------------------------------------------
        |
        | false = blocked/inactive user
        |
        */

        if (!$user->status) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact the administrator.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | SELLER APPROVAL CHECK
        |--------------------------------------------------------------------------
        */

        if ($user->hasRole('seller')) {

            /*
            | Get vendor profile
            */
            $vendor = $user->vendor;

            /*
            | Seller must have vendor profile
            */
            if (!$vendor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found. Please contact the administrator.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            |--------------------------------------------------------------------------
            |
            | Vendor status must be TRUE before seller can login.
            |
            */

            if (!$vendor->status) {

                return response()->json([
                    'success' => false,
                    'message' => 'Your seller account is waiting for admin approval.',
                    'approval_status' => 'pending',
                ], 403);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create Sanctum Token
        |--------------------------------------------------------------------------
        |
        | Token is created ONLY after all authorization checks pass.
        |
        */

        $token = $user
            ->createToken('auth_token')
            ->plainTextToken;

        /*
        |--------------------------------------------------------------------------
        | Login Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'user' => $user,
            'role' => $user->getRoleNames()->first(),
            'token' => $token,

            /*
            | Useful for React
            */
            'approved' => $user->hasRole('seller')
                ? true
                : null,
        ], 200);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        /*
        | Delete current token
        */
        $request->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}