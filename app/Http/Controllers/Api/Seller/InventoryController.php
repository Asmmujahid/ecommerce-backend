<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    /**
     * Constructor
     */
    public function __construct(
        InventoryService $inventoryService
    ) {
        $this->inventoryService = $inventoryService;
    }

    /**
     * =====================================================
     * GET SELLER INVENTORY
     * =====================================================
     *
     * GET /api/seller/inventories
     */
    public function index(
        Request $request
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Vendor profile not found.',
                'data' => [],
            ], 403);
        }

        try {

            $inventories =
                $this->inventoryService
                    ->sellerInventories(
                        $vendor->id
                    );

            return response()->json([
                'success' => true,
                'message' =>
                    'Inventory list retrieved successfully.',
                'data' => $inventories,
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to retrieve inventory.',
                'data' => [],
                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * =====================================================
     * CREATE INVENTORY
     * =====================================================
     *
     * POST /api/seller/inventories
     */
    public function store(
        Request $request
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Vendor profile not found.',
            ], 403);
        }

        $validated = $request->validate([
            'product_id' =>
                'required|exists:products,id',

            'product_variant_id' =>
                'nullable|exists:product_variants,id',

            'quantity' =>
                'required|integer|min:1',

            'type' =>
                'required|in:in,out',

            'note' =>
                'nullable|string|max:1000',
        ]);

        try {

            $inventory =
                $this->inventoryService
                    ->createSellerInventory(
                        $validated,
                        $vendor->id
                    );

            return response()->json([
                'success' => true,
                'message' =>
                    'Inventory updated successfully.',
                'data' => $inventory,
            ], 201);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    $e->getMessage(),
            ], 400);
        }
    }

    /**
     * =====================================================
     * SHOW INVENTORY
     * =====================================================
     *
     * GET /api/seller/inventories/{id}
     */
    public function show(
        Request $request,
        string $id
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Vendor profile not found.',
            ], 403);
        }

        try {

            $inventory =
                $this->inventoryService
                    ->findSellerInventory(
                        $vendor->id,
                        $id
                    );

            if (!$inventory) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Inventory not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $inventory,
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to retrieve inventory.',
                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * =====================================================
     * UPDATE INVENTORY
     * =====================================================
     *
     * PUT /api/seller/inventories/{id}
     *
     * Seller is allowed to update the inventory movement.
     */
    public function update(
        Request $request,
        string $id
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Vendor profile not found.',
            ], 403);
        }

        $inventory =
            $this->inventoryService
                ->findSellerInventory(
                    $vendor->id,
                    $id
                );

        if (!$inventory) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Inventory not found.',
            ], 404);
        }

        $validated = $request->validate([
            'quantity' =>
                'sometimes|integer|min:1',

            'type' =>
                'sometimes|in:in,out',

            'note' =>
                'nullable|string|max:1000',
        ]);

        try {

            $updatedInventory =
                $this->inventoryService
                    ->updateInventory(
                        $inventory,
                        $validated
                    );

            return response()->json([
                'success' => true,
                'message' =>
                    'Inventory record updated successfully.',
                'data' => $updatedInventory,
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    $e->getMessage(),
            ], 400);
        }
    }

    /**
     * =====================================================
     * DELETE INVENTORY
     * =====================================================
     *
     * DELETE /api/seller/inventories/{id}
     */
    public function destroy(
        Request $request,
        string $id
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
            ], 401);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Vendor profile not found.',
            ], 403);
        }

        $inventory =
            $this->inventoryService
                ->findSellerInventory(
                    $vendor->id,
                    $id
                );

        if (!$inventory) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Inventory not found.',
            ], 404);
        }

        try {

            $this->inventoryService
                ->deleteInventory($inventory);

            return response()->json([
                'success' => true,
                'message' =>
                    'Inventory record deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    $e->getMessage(),
            ], 400);
        }
    }
}