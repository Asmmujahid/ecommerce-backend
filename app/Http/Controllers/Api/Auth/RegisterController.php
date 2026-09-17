<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:customer,seller',

            // Seller fields
            'store_name' => 'required_if:role,seller|string|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | 1. Create User
            |--------------------------------------------------------------------------
            */

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),

                // Normal user account is active.
                'status' => true,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 2. CUSTOMER REGISTRATION
            |--------------------------------------------------------------------------
            */

            if ($validated['role'] === 'customer') {

                $user->assignRole('customer');

                /*
                | Customer can login immediately.
                */
                $token = $user
                    ->createToken('auth_token')
                    ->plainTextToken;

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Customer registration successful.',
                    'user' => $user->load('roles'),
                    'role' => 'customer',
                    'token' => $token,
                ], 201);
            }

            /*
            |--------------------------------------------------------------------------
            | 3. SELLER REGISTRATION
            |--------------------------------------------------------------------------
            */

            if ($validated['role'] === 'seller') {

                $user->assignRole('seller');

                /*
                |--------------------------------------------------------------------------
                | Create seller/vendor with PENDING status
                |--------------------------------------------------------------------------
                |
                | false = pending / not approved
                | true  = approved
                |
                */

                $vendor = Vendor::create([
                    'user_id' => $user->id,
                    'store_name' => $validated['store_name'],
                    'store_slug' => Str::slug($validated['store_name']),
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,

                    // IMPORTANT:
                    // Seller is NOT approved when registering.
                    'status' => false,
                ]);

                /*
                |--------------------------------------------------------------------------
                | IMPORTANT
                |--------------------------------------------------------------------------
                |
                | DO NOT CREATE A SANCTUM TOKEN FOR A PENDING SELLER.
                |
                */

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Seller registration successful. Your account is waiting for admin approval.',
                    'user' => $user->load('roles'),
                    'vendor' => $vendor,
                    'role' => 'seller',

                    // No token!
                    'token' => null,

                    'approved' => false,
                ], 201);
            }

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Registration failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}