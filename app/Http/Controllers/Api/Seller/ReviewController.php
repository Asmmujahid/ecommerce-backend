<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * =====================================================
     * GET SELLER REVIEWS
     *
     * GET /api/seller/reviews
     * =====================================================
     */
    public function index()
    {
        $user = auth()->user();

        $vendor = $user?->vendor;

        if (!$vendor) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Vendor account not found.',

                'data' => [],
            ], 404);
        }

        $reviews = Review::with([
            'user',
            'product',
            'product.vendor',
            'product.vendor.user',
        ])
            ->whereHas(
                'product',
                function ($query) use ($vendor) {

                    $query->where(
                        'vendor_id',
                        $vendor->id
                    );
                }
            )
            ->latest()
            ->get();

        return response()->json([
            'success' => true,

            'message' =>
                'Reviews retrieved successfully.',

            'data' =>
                $reviews,
        ], 200);
    }

    /**
     * =====================================================
     * SELLER CANNOT CREATE REVIEW
     * =====================================================
     */
    public function store(
        Request $request
    ) {
        return response()->json([
            'success' => false,

            'message' =>
                'Reviews are created by customers.',
        ], 403);
    }

    /**
     * =====================================================
     * SHOW SINGLE REVIEW
     *
     * GET /api/seller/reviews/{id}
     * =====================================================
     */
    public function show(string $id)
    {
        $vendor =
            auth()->user()?->vendor;

        if (!$vendor) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Vendor account not found.',
            ], 404);
        }

        $review = Review::with([
            'user',
            'product',
            'product.vendor',
            'product.vendor.user',
        ])
            ->whereHas(
                'product',
                function ($query) use ($vendor) {

                    $query->where(
                        'vendor_id',
                        $vendor->id
                    );
                }
            )
            ->find($id);

        if (!$review) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Review not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Review retrieved successfully.',

            'data' =>
                $review,
        ], 200);
    }

    /**
     * =====================================================
     * SELLER CANNOT UPDATE CUSTOMER REVIEW
     * =====================================================
     */
    public function update(
        Request $request,
        string $id
    ) {
        return response()->json([
            'success' => false,

            'message' =>
                'Customer reviews cannot be updated by sellers.',
        ], 403);
    }

    /**
     * =====================================================
     * SELLER CANNOT DELETE CUSTOMER REVIEW
     * =====================================================
     */
    public function destroy(string $id)
    {
        return response()->json([
            'success' => false,

            'message' =>
                'Customer reviews cannot be deleted by sellers.',
        ], 403);
    }
}