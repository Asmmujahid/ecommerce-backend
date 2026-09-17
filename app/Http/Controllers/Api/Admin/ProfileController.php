<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Display authenticated admin profile.
     *
     * GET /api/admin/profile
     */
    public function show()
    {
        $admin = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Admin profile retrieved successfully.',
            'data' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'avatar' => $admin->avatar,
                'status' => $admin->status,
                'roles' => $admin->getRoleNames(),
                'created_at' => $admin->created_at,
                'updated_at' => $admin->updated_at,
            ]
        ], 200);
    }

    /**
     * Update authenticated admin profile.
     *
     * PUT /api/admin/profile
     */
    public function update(Request $request)
    {
        $admin = auth()->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users')->ignore($admin->id),
            ],
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|string',
        ]);

        $admin->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Admin profile updated successfully.',
            'data' => $admin->fresh(),
        ], 200);
    }

    /**
     * Change authenticated admin password.
     *
     * POST /api/admin/change-password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $admin = auth()->user();

        if (!Hash::check($request->current_password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 400);
        }

        $admin->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ], 200);
    }
}