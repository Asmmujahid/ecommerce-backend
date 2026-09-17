<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Display all variants of seller product.
     *
     * GET /api/seller/products/{productId}/variants
     */
    public function index(string $productId)
    {
        $vendor = auth()->user()?->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
            ], 404);
        }

        $product = Product::where(
            'vendor_id',
            $vendor->id
        )
            ->with('variants')
            ->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product variants retrieved successfully.',
            'data' => $product->variants,
        ], 200);
    }

    /**
     * Store variant.
     *
     * POST /api/seller/products/{productId}/variants
     */
    public function store(Request $request, string $productId)
    {
        $validated = $request->validate([
            'sku' => [
                'required',
                'string',
                'unique:product_variants,sku',
            ],

            'size' => [
                'nullable',
                'string',
            ],

            'color' => [
                'nullable',
                'string',
            ],

            'price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'stock' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        $vendor = auth()->user()?->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
            ], 404);
        }

        $product = Product::where(
            'vendor_id',
            $vendor->id
        )->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $variant = $this->productService->addVariant(
            $product,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Variant created successfully.',
            'data' => $variant,
        ], 201);
    }

    /**
     * Show variant.
     *
     * GET /api/seller/product-variants/{id}
     */
    public function show(string $id)
    {
        $vendor = auth()->user()?->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
            ], 404);
        }

        $variant = ProductVariant::with('product')
            ->whereHas('product', function ($query) use ($vendor) {
                $query->where(
                    'vendor_id',
                    $vendor->id
                );
            })
            ->find($id);

        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Variant retrieved successfully.',
            'data' => $variant,
        ], 200);
    }

    /**
     * Update variant.
     *
     * PUT /api/seller/product-variants/{id}
     */
    public function update(Request $request, string $id)
    {
        $vendor = auth()->user()?->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
            ], 404);
        }

        $variant = ProductVariant::whereHas(
            'product',
            function ($query) use ($vendor) {
                $query->where(
                    'vendor_id',
                    $vendor->id
                );
            }
        )->find($id);

        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found.',
            ], 404);
        }

        $validated = $request->validate([
            'sku' => [
                'sometimes',
                'string',
                'unique:product_variants,sku,' . $id,
            ],

            'size' => [
                'nullable',
                'string',
            ],

            'color' => [
                'nullable',
                'string',
            ],

            'price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'stock' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        $variant = $this->productService->updateVariant(
            $variant,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully.',
            'data' => $variant,
        ], 200);
    }

    /**
     * Delete variant.
     *
     * DELETE /api/seller/product-variants/{id}
     */
    public function destroy(string $id)
    {
        $vendor = auth()->user()?->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
            ], 404);
        }

        $variant = ProductVariant::whereHas(
            'product',
            function ($query) use ($vendor) {
                $query->where(
                    'vendor_id',
                    $vendor->id
                );
            }
        )->find($id);

        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found.',
            ], 404);
        }

        $this->productService->deleteVariant($variant);

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted successfully.',
        ], 200);
    }
}
