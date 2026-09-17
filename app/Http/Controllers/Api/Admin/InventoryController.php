<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
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
     * GET ALL INVENTORY RECORDS
     * =====================================================
     *
     * GET /api/admin/inventories
     */
    public function index(): JsonResponse
    {
        try {
            $inventories =
                $this->inventoryService
                    ->adminInventories();

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
                    'Failed to retrieve inventory records.',
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
     * STORE INVENTORY
     * =====================================================
     *
     * POST /api/admin/inventories
     */
    public function store(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'product_id' =>
                'nullable|exists:products,id',

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
                    ->createInventory($validated);

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
     * SHOW SINGLE INVENTORY
     * =====================================================
     *
     * GET /api/admin/inventories/{id}
     */
    public function show(
        string $id
    ): JsonResponse {
        try {

            $inventory =
                $this->inventoryService
                    ->findInventory($id);

            if (!$inventory) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Inventory not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' =>
                    'Inventory retrieved successfully.',
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
     * PUT /api/admin/inventories/{id}
     */
    public function update(
        Request $request,
        string $id
    ): JsonResponse {

        $inventory =
            Inventory::find($id);

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
     * DELETE /api/admin/inventories/{id}
     */
    public function destroy(
        string $id
    ): JsonResponse {

        $inventory =
            Inventory::find($id);

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