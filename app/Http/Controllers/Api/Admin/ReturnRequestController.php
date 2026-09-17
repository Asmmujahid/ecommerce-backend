<?php

namespace App\Http\Controllers\Api\Admin;

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
            'orderItem.product.vendor.user',
            'orderItem.productVariant',
        ];
    }

    /**
     * =====================================================
     * INDEX
     * =====================================================
     */
    public function index()
    {
        $requests = ReturnRequest::with(
            $this->relations()
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
        $returnRequest =
            ReturnRequest::with(
                $this->relations()
            )->find($id);

        if (!$returnRequest) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Return request not found.',
                'data' => null,
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
     * PUT /api/admin/return-requests/{id}
     */
    public function update(
        Request $request,
        string $id
    ) {
        $returnRequest =
            ReturnRequest::find($id);

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
                'in:pending,approved,rejected,refunded',
            ],

            'refund_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'admin_note' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        /*
         * Rejected request does not have
         * a refund amount.
         */
        if (
            $validated['status'] ===
            'rejected'
        ) {
            $validated['refund_amount'] =
                null;
        }

        /*
         * Update.
         */
        $returnRequest->update(
            $validated
        );

        /*
         * Reload relationships.
         */
        $returnRequest->load(
            $this->relations()
        );

        /**
         * =====================================================
         * NOTIFY CUSTOMER
         * =====================================================
         *
         * This is the important part.
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

    /**
     * =====================================================
     * DELETE
     * =====================================================
     */
    public function destroy(string $id)
    {
        $returnRequest =
            ReturnRequest::find($id);

        if (!$returnRequest) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Return request not found.',
            ], 404);
        }

        $returnRequest->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Return request deleted successfully.',
        ], 200);
    }
}