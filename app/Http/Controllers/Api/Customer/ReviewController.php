<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewReceivedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    /**
     * =========================================================
     * GET ALL REVIEWS WRITTEN BY CURRENT CUSTOMER
     *
     * GET /api/customer/reviews
     * =========================================================
     */
    public function index()
    {
        $userId = auth()->id();

        $reviews = Review::with([
            'user',
            'product',
            'product.vendor',
            'product.vendor.user',
        ])
            ->where(
                'user_id',
                $userId
            )
            ->latest()
            ->get();

        return response()->json([
            'success' => true,

            'message' =>
                'Reviews retrieved successfully.',

            'data' => $reviews,
        ], 200);
    }

    /**
     * =========================================================
     * GET PRODUCTS CUSTOMER CAN REVIEW
     *
     * GET /api/customer/reviews/reviewable-products
     * =========================================================
     */
    public function reviewableProducts()
    {
        $userId = auth()->id();

        /*
        |--------------------------------------------------------------------------
        | Products already reviewed
        |--------------------------------------------------------------------------
        */

        $reviewedProductIds =
            Review::where(
                'user_id',
                $userId
            )
                ->pluck('product_id')
                ->toArray();

        /*
        |--------------------------------------------------------------------------
        | Delivered / completed orders
        |--------------------------------------------------------------------------
        */

        $orders = Order::with([
            'items.product',
            'items.productVariant',
        ])
            ->where(
                'user_id',
                $userId
            )
            ->whereIn(
                'status',
                [
                    'delivered',
                    'completed',
                ]
            )
            ->latest()
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Build products
        |--------------------------------------------------------------------------
        */

        $products = collect();

        foreach ($orders as $order) {

            foreach ($order->items as $item) {

                if (!$item->product) {
                    continue;
                }

                if (
                    in_array(
                        $item->product_id,
                        $reviewedProductIds
                    )
                ) {
                    continue;
                }

                $product = $item->product;

                if (
                    !$products->contains(
                        'id',
                        $product->id
                    )
                ) {
                    $products->push([
                        'id' =>
                            $product->id,

                        'name' =>
                            $product->name,

                        'price' =>
                            $product->price,

                        'discount_price' =>
                            $product->discount_price,

                        'image' =>
                            $product->image,

                        'product_id' =>
                            $product->id,

                        'order_id' =>
                            $order->id,

                        'order_item_id' =>
                            $item->id,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Reviewable products retrieved successfully.',

            'data' =>
                $products->values(),
        ], 200);
    }

    /**
     * =========================================================
     * CREATE REVIEW
     *
     * POST /api/customer/reviews
     * =========================================================
     */
    public function store(Request $request)
    {
        $userId = auth()->id();

        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'rating' => [
                'required',
                'integer',
                'min:1',
                'max:5',
            ],

            'comment' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Check duplicate review
        |--------------------------------------------------------------------------
        */

        $alreadyReviewed =
            Review::where(
                'user_id',
                $userId
            )
                ->where(
                    'product_id',
                    $validated['product_id']
                )
                ->exists();

        if ($alreadyReviewed) {

            return response()->json([
                'success' => false,

                'message' =>
                    'You have already reviewed this product.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify purchased product
        |--------------------------------------------------------------------------
        */

        $hasPurchased =
            DB::table('orders')
                ->join(
                    'order_items',
                    'orders.id',
                    '=',
                    'order_items.order_id'
                )
                ->where(
                    'orders.user_id',
                    $userId
                )
                ->where(
                    'order_items.product_id',
                    $validated['product_id']
                )
                ->whereIn(
                    'orders.status',
                    [
                        'delivered',
                        'completed',
                    ]
                )
                ->exists();

        if (!$hasPurchased) {

            return response()->json([
                'success' => false,

                'message' =>
                    'You can only review products from delivered or completed orders.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Create review
        |--------------------------------------------------------------------------
        */

        try {

            $review = DB::transaction(
                function () use (
                    $validated,
                    $userId
                ) {

                    return Review::create([
                        'user_id' =>
                            $userId,

                        'product_id' =>
                            $validated['product_id'],

                        'rating' =>
                            $validated['rating'],

                        'comment' =>
                            isset(
                                $validated['comment']
                            )
                                ? trim(
                                    $validated['comment']
                                )
                                : null,

                        'status' =>
                            'active',
                    ]);
                }
            );

        } catch (\Throwable $e) {

            Log::error(
                'Failed to create review.',
                [
                    'user_id' =>
                        $userId,

                    'product_id' =>
                        $validated['product_id'],

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Failed to create review.',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | LOAD ALL REQUIRED RELATIONSHIPS
        |--------------------------------------------------------------------------
        |
        | This is the important part.
        |
        | product
        |   └── vendor
        |         └── user
        |
        |--------------------------------------------------------------------------
        */

        $review->load([
            'user',
            'product',
            'product.vendor',
            'product.vendor.user',
        ]);

        /*
        |--------------------------------------------------------------------------
        | NOTIFY SELLER
        |--------------------------------------------------------------------------
        */

        try {

            $seller =
                $review
                    ->product
                    ?->vendor
                    ?->user;

            if ($seller) {

                $seller->notify(
                    new ReviewReceivedNotification(
                        $review
                    )
                );
            }

        } catch (\Throwable $e) {

            Log::error(
                'Seller review notification failed.',
                [
                    'review_id' =>
                        $review->id,

                    'product_id' =>
                        $review->product_id,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | NOTIFY ADMINS
        |--------------------------------------------------------------------------
        */

        try {

            /*
             * Spatie Permission
             *
             * User::role('admin')
             */

            $admins = User::role('admin')->get();

            foreach ($admins as $admin) {

                $admin->notify(
                    new ReviewReceivedNotification(
                        $review
                    )
                );
            }

        } catch (\Throwable $e) {

            Log::error(
                'Admin review notification failed.',
                [
                    'review_id' =>
                        $review->id,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'message' =>
                'Review added successfully.',

            'data' =>
                $review,
        ], 201);
    }

    /**
     * =========================================================
     * SHOW SINGLE REVIEW
     *
     * GET /api/customer/reviews/{id}
     * =========================================================
     */
    public function show(string $id)
    {
        $review = Review::with([
            'user',
            'product',
            'product.vendor',
            'product.vendor.user',
        ])
            ->where(
                'user_id',
                auth()->id()
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
     * =========================================================
     * UPDATE REVIEW
     *
     * PUT /api/customer/reviews/{id}
     * =========================================================
     */
    public function update(
        Request $request,
        string $id
    ) {
        $review = Review::where(
            'user_id',
            auth()->id()
        )
            ->find($id);

        if (!$review) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Review not found.',
            ], 404);
        }

        $validated = $request->validate([
            'rating' => [
                'sometimes',
                'integer',
                'min:1',
                'max:5',
            ],

            'comment' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $review->update([
            'rating' =>
                $validated['rating']
                ?? $review->rating,

            'comment' =>
                array_key_exists(
                    'comment',
                    $validated
                )
                    ? (
                        $validated['comment'] !== null
                            ? trim(
                                $validated['comment']
                            )
                            : null
                    )
                    : $review->comment,
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
     * =========================================================
     * DELETE REVIEW
     *
     * DELETE /api/customer/reviews/{id}
     * =========================================================
     */
    public function destroy(string $id)
    {
        $review = Review::where(
            'user_id',
            auth()->id()
        )
            ->find($id);

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