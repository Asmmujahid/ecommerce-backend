<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Notifications\ReturnRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReturnRequestController extends Controller
{
    /**
     * ============================================================
     * COMMON RELATIONSHIPS
     * ============================================================
     */
    private function relations(): array
    {
        return [
            'user',
            'order',
            'orderItem',
            'orderItem.product',
            'orderItem.product.vendor',
            'orderItem.product.vendor.user',
            'orderItem.productVariant',
        ];
    }

    /**
     * ============================================================
     * INDEX
     * ============================================================
     *
     * GET /api/customer/return-requests
     */
    public function index()
    {
        $userId = Auth::id();

        $returns = ReturnRequest::with(
            $this->relations()
        )
            ->where('user_id', $userId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Return requests retrieved successfully.',
            'data' => $returns,
        ], 200);
    }

    /**
     * ============================================================
     * RETURNABLE ITEMS
     * ============================================================
     *
     * GET /api/customer/return-requests/returnable-items
     */
    public function returnableItems()
    {
        $userId = Auth::id();

        $items = OrderItem::with([
            'order',
            'product',
            'productVariant',
        ])
            ->whereHas(
                'order',
                function ($query) use ($userId) {
                    $query
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
                        );
                }
            )
            ->whereDoesntHave(
                'returnRequest'
            )
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Returnable order items retrieved successfully.',
            'data' => $items,
        ], 200);
    }

    /**
     * ============================================================
     * STORE
     * ============================================================
     *
     * POST /api/customer/return-requests
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'order_id' => [
                'required',
                'integer',
                'exists:orders,id',
            ],

            'order_item_id' => [
                'required',
                'integer',
                'exists:order_items,id',
            ],

            'reason' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ]);

        /**
         * ========================================================
         * VERIFY ORDER ITEM
         * ========================================================
         */
        $orderItem = OrderItem::with([
            'order',
            'product',
            'productVariant',
            'product.vendor',
            'product.vendor.user',
        ])
            ->where(
                'id',
                $validated['order_item_id']
            )
            ->where(
                'order_id',
                $validated['order_id']
            )
            ->whereHas(
                'order',
                function ($query) use ($user) {
                    $query
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->whereIn(
                            'status',
                            [
                                'delivered',
                                'completed',
                            ]
                        );
                }
            )
            ->first();

        if (!$orderItem) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This product is not eligible for return. The order must belong to you and must be delivered.',
            ], 422);
        }

        /**
         * ========================================================
         * DUPLICATE CHECK
         * ========================================================
         */
        $existingReturn =
            ReturnRequest::where(
                'user_id',
                $user->id
            )
                ->where(
                    'order_id',
                    $validated['order_id']
                )
                ->where(
                    'order_item_id',
                    $validated['order_item_id']
                )
                ->first();

        if ($existingReturn) {
            $existingReturn->load(
                $this->relations()
            );

            return response()->json([
                'success' => false,
                'already_submitted' => true,
                'message' =>
                    'A return request has already been submitted for this product.',
                'data' => $existingReturn,
            ], 409);
        }

        /**
         * ========================================================
         * CREATE RETURN REQUEST
         * ========================================================
         */
        $returnRequest = DB::transaction(
            function () use (
                $user,
                $validated
            ) {
                return ReturnRequest::create([
                    'user_id' =>
                        $user->id,

                    'order_id' =>
                        $validated['order_id'],

                    'order_item_id' =>
                        $validated['order_item_id'],

                    'reason' =>
                        trim(
                            $validated['reason']
                        ),

                    'status' =>
                        'pending',

                    'refund_amount' =>
                        null,

                    'admin_note' =>
                        null,
                ]);
            }
        );

        /**
         * ========================================================
         * LOAD RELATIONSHIPS
         * ========================================================
         */
        $returnRequest->load(
            $this->relations()
        );

        /**
         * ========================================================
         * NOTIFY ADMINS
         * ========================================================
         */
        try {
            $admins = User::role(
                'admin'
            )->get();

            foreach ($admins as $admin) {
                $admin->notify(
                    new ReturnRequestNotification(
                        $returnRequest,
                        'submitted'
                    )
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        /**
         * ========================================================
         * NOTIFY SELLER
         * ========================================================
         */
        try {
            $seller =
                $orderItem
                    ->product
                    ?->vendor
                    ?->user;

            if ($seller) {
                $seller->notify(
                    new ReturnRequestNotification(
                        $returnRequest,
                        'submitted'
                    )
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'already_submitted' => false,
            'message' =>
                'Return request submitted successfully.',
            'data' => $returnRequest,
        ], 201);
    }

    /**
     * ============================================================
     * SHOW
     * ============================================================
     *
     * GET /api/customer/return-requests/{id}
     */
    public function show(string $id)
    {
        $userId = Auth::id();

        $returnRequest =
            ReturnRequest::with(
                $this->relations()
            )
                ->where(
                    'user_id',
                    $userId
                )
                ->find($id);

        if (!$returnRequest) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Return request not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Return request retrieved successfully.',
            'data' => $returnRequest,
        ], 200);
    }

    /**
     * ============================================================
     * CANCEL
     * ============================================================
     *
     * DELETE /api/customer/return-requests/{id}
     */
    public function destroy(string $id)
    {
        $userId = Auth::id();

        $returnRequest =
            ReturnRequest::where(
                'user_id',
                $userId
            )->find($id);

        if (!$returnRequest) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Return request not found.',
            ], 404);
        }

        if (
            $returnRequest->status !==
            'pending'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Only pending return requests can be cancelled.',
            ], 422);
        }

        $returnRequest->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Return request cancelled successfully.',
        ], 200);
    }
}