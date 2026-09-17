<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    /**
     * GET ALL SELLERS (VENDORS)
     */
    public function index()
    {
        $vendors = Vendor::with('user')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Vendor list retrieved successfully',
            'data' => $vendors
        ]);
    }

    /**
     * SHOW SINGLE SELLER
     */
    public function show($id)
    {
        $vendor = Vendor::with('user')->find($id);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $vendor
        ]);
    }

    /**
     * UPDATE SELLER (APPROVAL + INFO UPDATE)
     */
    public function update(Request $request, $id)
    {
        $vendor = Vendor::find($id);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $validated = $request->validate([
            'store_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
            'banner' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'status' => 'required|boolean'
        ]);

        // Update slug if store name changes
        if (isset($validated['store_name'])) {
            $validated['store_slug'] = Str::slug($validated['store_name']);
        }

        $vendor->update($validated);

        /**
         * =========================
         * APPROVAL LOGIC
         * =========================
         */
        if ($request->status == true) {
            $vendor->user->assignRole('seller');
        }

        return response()->json([
            'success' => true,
            'message' => $request->status
                ? 'Seller approved successfully'
                : 'Seller rejected successfully',
            'data' => $vendor->load('user')
        ]);
    }

    /**
     * DELETE SELLER
     */
    public function destroy($id)
    {
        $vendor = Vendor::find($id);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $vendor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vendor deleted successfully'
        ]);
    }
}