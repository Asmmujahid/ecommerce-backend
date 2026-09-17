<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    /**
     * GET STORE DETAILS
     */
    public function show()
    {
        $vendor = auth()->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Store details retrieved successfully',
            'data' => $vendor
        ], 200);
    }

    /**
     * UPDATE STORE DETAILS
     */
    public function update(Request $request)
    {
        $vendor = auth()->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        $validated = $request->validate([
            'store_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
            'banner' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        if (isset($validated['store_name'])) {
            $validated['store_slug'] = Str::slug($validated['store_name']);
        }

        $vendor->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Store updated successfully',
            'data' => $vendor->fresh()
        ], 200);
    }
}