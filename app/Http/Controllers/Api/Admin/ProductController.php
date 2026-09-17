<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Display all products for admin.
     *
     * GET /api/admin/products
     */
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully.',
            'data' => $this->productService->adminProducts($request),
        ], 200);
    }

    /**
     * Store product.
     *
     * ADMIN OWNS THIS PRODUCT.
     *
     * Vendor is selected as the selling channel.
     *
     * POST /api/admin/products
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_id' => [
                'required',
                'exists:vendors,id',
            ],

            'category_id' => [
                'required',
                'exists:categories,id',
            ],

            'brand_id' => [
                'required',
                'exists:brands,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'sku' => [
                'required',
                'string',
                'unique:products,sku',
            ],

            'short_description' => [
                'nullable',
                'string',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'discount_price' => [
                'nullable',
                'numeric',
                'min:0',
                'lte:price',
            ],

            'stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'thumbnail' => [
                'nullable',
                'string',
            ],

            'status' => [
                'sometimes',
                'boolean',
            ],

            'featured' => [
                'sometimes',
                'boolean',
            ],

            'images' => [
                'nullable',
                'array',
            ],

            'images.*.image' => [
                'required',
                'string',
            ],

            'variants' => [
                'nullable',
                'array',
            ],

            'variants.*.sku' => [
                'required',
                'string',
                'distinct',
                'unique:product_variants,sku',
            ],

            'variants.*.size' => [
                'nullable',
                'string',
            ],

            'variants.*.color' => [
                'nullable',
                'string',
            ],

            'variants.*.price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'variants.*.stock' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        /*
         * Admin-created product.
         *
         * vendor_id = selected Vendor Store
         * owner_type = admin
         */
        $product = $this->productService->createProduct(
            $validated,
            $validated['vendor_id'],
            'admin'
        );

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => $product,
        ], 201);
    }

    /**
     * Show product.
     *
     * GET /api/admin/products/{id}
     */
    public function show(string $id)
    {
        $product = Product::with([
            'category',
            'brand',
            'vendor',
            'images',
            'variants',
            'reviews.user',
        ])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully.',
            'data' => $product,
        ], 200);
    }

    /**
     * Update product.
     *
     * Admin can change:
     * - vendor selling channel
     * - product information
     *
     * Ownership remains ADMIN.
     *
     * PUT /api/admin/products/{id}
     */
    public function update(Request $request, string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
                'data' => null,
            ], 404);
        }

        $validated = $request->validate([
            'vendor_id' => [
                'sometimes',
                'exists:vendors,id',
            ],

            'category_id' => [
                'sometimes',
                'exists:categories,id',
            ],

            'brand_id' => [
                'sometimes',
                'exists:brands,id',
            ],

            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'sku' => [
                'sometimes',
                'string',
                'unique:products,sku,' . $id,
            ],

            'short_description' => [
                'nullable',
                'string',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'price' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'discount_price' => [
                'nullable',
                'numeric',
                'min:0',
                'lte:price',
            ],

            'stock' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'thumbnail' => [
                'nullable',
                'string',
            ],

            'status' => [
                'sometimes',
                'boolean',
            ],

            'featured' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $product = $this->productService->updateProduct(
            $product,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => $product,
        ], 200);
    }

    /**
     * Delete product.
     *
     * DELETE /api/admin/products/{id}
     */
    public function destroy(string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $this->productService->deleteProduct($product);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ], 200);
    }
}

