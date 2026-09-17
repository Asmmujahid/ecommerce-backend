<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Notifications\ReturnRequestNotification;
use Illuminate\Http\Request;

class ReturnRequestController extends Controller
{
    /**
     * =====================================================
     * RELATIONSHIPS
     * =====================================================
     */
    private function relations(): array
    {
        return [
            'user',
            'order',
            'orderItem',
            'orderItem.product',
            'orderItem.product.vendor',
            'orderItem.productVariant',
        ];
    }

    /**
     * =====================================================
     * GET ALL
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

        $requests =
            ReturnRequest::with(
                $this->relations()
            )
                ->whereHas(
                    'orderItem.product',
                    function ($query) use (
                        $vendor
                    ) {
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
                'Return requests retrieved successfully.',
            'data' => $requests,
        ], 200);
    }

    /**
     * =====================================================
     * SHOW
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

        $returnRequest =
            ReturnRequest::with(
                $this->relations()
            )
                ->whereHas(
                    'orderItem.product',
                    function ($query) use (
                        $vendor
                    ) {
                        $query->where(
                            'vendor_id',
                            $vendor->id
                        );
                    }
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
     * =====================================================
     * UPDATE
     * =====================================================
     *
     * PUT /api/seller/return-requests/{id}
     */
    public function update(
        Request $request,
        string $id
    ) {
        $vendor =
            auth()->user()?->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Vendor account not found.',
            ], 404);
        }

        /*
         * Only allow this seller to update
         * returns for their own products.
         */
        $returnRequest =
            ReturnRequest::whereHas(
                'orderItem.product',
                function ($query) use (
                    $vendor
                ) {
                    $query->where(
                        'vendor_id',
                        $vendor->id
                    );
                }
            )
                ->find($id);

        if (!$returnRequest) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Return request not found.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:approved,rejected',
            ],

            'admin_note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        /*
         * Update status.
         */
        $returnRequest->update([
            'status' =>
                $validated['status'],

            'admin_note' =>
                $validated['admin_note']
                ?? null,
        ]);

        /*
         * Reload complete relationships.
         */
        $returnRequest->load(
            $this->relations()
        );

        /**
         * =====================================================
         * NOTIFY CUSTOMER
         * =====================================================
         */
        try {
            $customer =
                $returnRequest->user;

            if ($customer) {
                $customer->notify(
                    new ReturnRequestNotification(
                        $returnRequest,
                        'updated'
                    )
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Return request updated successfully.',
            'data' => $returnRequest,
        ], 200);
    }
}