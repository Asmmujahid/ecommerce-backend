<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * =====================================================
     * GET ALL REVIEWS
     *
     * GET /api/admin/reviews
     * =====================================================
     */
    public function index()
    {
        $reviews = Review::with([
            'user',
            'product',
            'product.vendor',
            'product.vendor.user',
        ])
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
     * SHOW REVIEW
     *
     * GET /api/admin/reviews/{id}
     * =====================================================
     */
    public function show(string $id)
    {
        $review = Review::with([
            'user',
            'product',
            'product.vendor',
            'product.vendor.user',
        ])
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
     * UPDATE REVIEW STATUS
     *
     * PUT /api/admin/reviews/{id}
     * =====================================================
     */
    public function update(
        Request $request,
        string $id
    ) {
        $review = Review::find($id);

        if (!$review) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Review not found.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'in:active,hidden,flagged',
            ],
        ]);

        $review->update([
            'status' =>
                $validated['status'],
        ]);

        $review->load([
            'user',
            'product',
            'product.vendor',
            'product.vendor.user',
        ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Review updated successfully.',

            'data' =>
                $review,
        ], 200);
    }

    /**
     * =====================================================
     * DELETE REVIEW
     *
     * DELETE /api/admin/reviews/{id}
     * =====================================================
     */
    public function destroy(string $id)
    {
        $review = Review::find($id);

        if (!$review) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Review not found.',
            ], 404);
        }

        $review->delete();

        return response()->json([
            'success' => true,

            'message' =>
                'Review deleted successfully.',
        ], 200);
    }
}