<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Display all images of seller product.
     *
     * GET /api/seller/products/{productId}/images
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
            ->with('images')
            ->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product images retrieved successfully.',
            'data' => $product->images,
        ], 200);
    }

    /**
     * Store image.
     *
     * POST /api/seller/products/{productId}/images
     */
    public function store(Request $request, string $productId)
    {
        $validated = $request->validate([
            'image' => [
                'required',
                'string',
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

        $image = $this->productService->addImage(
            $product,
            $validated['image']
        );

        return response()->json([
            'success' => true,
            'message' => 'Image created successfully.',
            'data' => $image,
        ], 201);
    }

    /**
     * Show image.
     *
     * GET /api/seller/product-images/{id}
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

        $image = ProductImage::with('product')
            ->whereHas('product', function ($query) use ($vendor) {
                $query->where('vendor_id', $vendor->id);
            })
            ->find($id);

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Image retrieved successfully.',
            'data' => $image,
        ], 200);
    }

    /**
     * Update image.
     *
     * PUT /api/seller/product-images/{id}
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'image' => [
                'required',
                'string',
            ],
        ]);

        $vendor = auth()->user()?->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
            ], 404);
        }

        $image = ProductImage::whereHas(
            'product',
            function ($query) use ($vendor) {
                $query->where(
                    'vendor_id',
                    $vendor->id
                );
            }
        )->find($id);

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found.',
            ], 404);
        }

        $image = $this->productService->updateImage(
            $image,
            $validated['image']
        );

        return response()->json([
            'success' => true,
            'message' => 'Image updated successfully.',
            'data' => $image,
        ], 200);
    }

    /**
     * Delete image.
     *
     * DELETE /api/seller/product-images/{id}
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

        $image = ProductImage::whereHas(
            'product',
            function ($query) use ($vendor) {
                $query->where(
                    'vendor_id',
                    $vendor->id
                );
            }
        )->find($id);

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found.',
            ], 404);
        }

        $this->productService->deleteImage($image);

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully.',
        ], 200);
    }
}
