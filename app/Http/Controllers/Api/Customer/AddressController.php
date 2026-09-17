<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Display all customer addresses
     */
    public function index()
    {
        $addresses = Address::where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Address list retrieved successfully',
            'data' => $addresses
        ]);
    }

    /**
     * Store new address
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address_line' => 'required|string',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:255',
            'type' => 'required|in:home,office',
            'is_default' => 'boolean'
        ]);

        // Remove previous default address
        if (($validated['is_default'] ?? false) == true) {
            Address::where('user_id', auth()->id())
                ->update(['is_default' => false]);
        }

        $address = Address::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'address_line' => $validated['address_line'],
            'city' => $validated['city'],
            'state' => $validated['state'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'],
            'type' => $validated['type'],
            'is_default' => $validated['is_default'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Address created successfully',
            'data' => $address
        ], 201);
    }

    /**
     * Show single address
     */
    public function show(string $id)
    {
        $address = Address::where('user_id', auth()->id())
            ->find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $address
        ]);
    }

    /**
     * Update address
     */
    public function update(Request $request, string $id)
    {
        $address = Address::where('user_id', auth()->id())
            ->find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'address_line' => 'sometimes|string',
            'city' => 'sometimes|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:home,office',
            'is_default' => 'boolean'
        ]);

        // Set only one default address
        if (($validated['is_default'] ?? false) == true) {
            Address::where('user_id', auth()->id())
                ->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully',
            'data' => $address
        ]);
    }

    /**
     * Delete address
     */
    public function destroy(string $id)
    {
        $address = Address::where('user_id', auth()->id())
            ->find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found'
            ], 404);
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully'
        ]);
    }
}