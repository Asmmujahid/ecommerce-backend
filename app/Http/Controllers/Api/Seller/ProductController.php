<?php

namespace App\Http\Controllers\Api\Seller;

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
     * Display all products available in the seller's Vendor Store.
     *
     * IMPORTANT:
     *
     * This includes:
     *
     * 1. Vendor-owned products
     *    owner_type = vendor
     *
     * 2. Admin-owned products assigned to this Vendor Store
     *    owner_type = admin
     *
     * Visibility is based on vendor_id.
     *
     * Seller management permissions are handled separately.
     *
     * GET /api/seller/products
     */
    public function index(Request $request)
    {
        $vendor = auth()->user()?->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
                'data' => null,
            ], 404);
        }

        $products = $this->productService->sellerProducts(
            $vendor->id,
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully.',
            'data' => $products,
        ], 200);
    }

    /**
     * Create seller-owned product.
     *
     * owner_type = vendor
     *
     * POST /api/seller/products
     */
    public function store(Request $request)
    {
        $vendor = auth()->user()?->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
                'data' => null,
            ], 404);
        }

        $validated = $request->validate([
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
         * Seller-created product.
         *
         * Current seller becomes:
         *
         * vendor_id = current vendor
         * owner_type = vendor
         */
        $product = $this->productService->createProduct(
            $validated,
            $vendor->id,
            'vendor'
        );

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => $product,
        ], 201);
    }

    /**
     * Show seller-owned product.
     *
     * IMPORTANT:
     *
     * Admin-owned products may be visible in the seller
     * product list, but seller cannot manage them.
     *
     * Therefore this endpoint only allows vendor-owned products.
     *
     * GET /api/seller/products/{id}
     */
    public function show(string $id)
    {
        $vendor = auth()->user()?->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
                'data' => null,
            ], 404);
        }

        $product = Product::with([
            'category',
            'brand',
            'vendor',
            'images',
            'variants',
            'reviews.user',
        ])
            ->where(
                'vendor_id',
                $vendor->id
            )
            ->where(
                'owner_type',
                'vendor'
            )
            ->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found or you do not have permission to manage this product.',
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
     * Update seller-owned product.
     *
     * Seller can ONLY update products they own.
     *
     * Admin-owned products assigned to the seller
     * cannot be updated by the seller.
     *
     * PUT /api/seller/products/{id}
     */
    public function update(Request $request, string $id)
    {
        $vendor = auth()->user()?->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
                'data' => null,
            ], 404);
        }

        $product = Product::where(
            'vendor_id',
            $vendor->id
        )
            ->where(
                'owner_type',
                'vendor'
            )
            ->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found or you do not have permission to update this product.',
                'data' => null,
            ], 404);
        }

        $validated = $request->validate([
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

        /*
         * Never allow seller to change:
         *
         * vendor_id
         * owner_type
         *
         * They are not part of this validation.
         */
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
     * Delete seller-owned product.
     *
     * Seller can ONLY delete products they own.
     *
     * DELETE /api/seller/products/{id}
     */
    public function destroy(string $id)
    {
        $vendor = auth()->user()?->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
                'data' => null,
            ], 404);
        }

        $product = Product::where(
            'vendor_id',
            $vendor->id
        )
            ->where(
                'owner_type',
                'vendor'
            )
            ->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found or you do not have permission to delete this product.',
                'data' => null,
            ], 404);
        }

        $this->productService->deleteProduct(
            $product
        );

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ], 200);
    }
}

