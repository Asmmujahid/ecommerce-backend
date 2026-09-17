<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Display customer's wishlist.
     */
    public function index()
    {
        $wishlists = Wishlist::with([
            'product.category',
            'product.brand',
            'product.images',
            'product.variants'
        ])
        ->where('user_id', auth()->id())
        ->latest()
        ->get();

        return response()->json([
            'success' => true,
            'message' => 'Wishlist retrieved successfully',
            'data' => $wishlists
        ], 200);
    }

    /**
     * Add product to wishlist.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        // Prevent duplicate wishlist items
        $exists = Wishlist::where('user_id', auth()->id())
            ->where('product_id', $validated['product_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Product already exists in wishlist'
            ], 409);
        }

        $wishlist = Wishlist::create([
            'user_id' => auth()->id(),
            'product_id' => $validated['product_id']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product added to wishlist successfully',
            'data' => $wishlist->load([
                'product.category',
                'product.brand',
                'product.images',
                'product.variants'
            ])
        ], 201);
    }

    /**
     * Show single wishlist item.
     */
    public function show(string $id)
    {
        $wishlist = Wishlist::with([
            'product.category',
            'product.brand',
            'product.images',
            'product.variants'
        ])
        ->where('user_id', auth()->id())
        ->find($id);

        if (!$wishlist) {
            return response()->json([
                'success' => false,
                'message' => 'Wishlist item not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $wishlist
        ], 200);
    }

    /**
     * Update method not required.
     */
    public function update(Request $request, string $id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Update operation is not supported for wishlist'
        ], 405);
    }

    /**
     * Remove product from wishlist.
     */
    public function destroy(string $id)
    {
        $wishlist = Wishlist::where('user_id', auth()->id())
            ->find($id);

        if (!$wishlist) {
            return response()->json([
                'success' => false,
                'message' => 'Wishlist item not found'
            ], 404);
        }

        $wishlist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product removed from wishlist successfully'
        ], 200);
    }
}