<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Throwable;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Display all active products.
     *
     * GET /api/products
     */
    public function index()
    {
        try {
            $products = $this->productService->customerProducts();

            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully.',
                'data' => $products,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve products.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search products.
     *
     * POST /api/products/search
     */
    public function search(Request $request)
    {
        try {
            $keyword = $request->input('keyword', '');

            if (is_array($keyword)) {
                $keyword =
                    $keyword['keyword']
                    ?? $keyword['search']
                    ?? $keyword['q']
                    ?? '';
            }

            $keyword = is_string($keyword)
                ? trim($keyword)
                : '';

            $products = $this->productService->searchProducts(
                $keyword
            );

            return response()->json([
                'success' => true,
                'message' => 'Search results retrieved successfully.',
                'data' => $products,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search products.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show single active product.
     *
     * GET /api/products/{id}
     */
    public function show(string $id)
    {
        try {
            $product = $this->productService->showProduct($id);

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
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve product.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Filter products.
     *
     * POST /api/products/filter
     */
    public function filter(Request $request)
    {
        try {
            $filters = [
                'category_id' => $request->input('category_id'),
                'brand_id' => $request->input('brand_id'),
                'featured' => $request->input('featured'),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'in_stock' => $request->input('in_stock'),
            ];

            $products = $this->productService->filterProducts(
                $filters
            );

            return response()->json([
                'success' => true,
                'message' => 'Filtered products retrieved successfully.',
                'data' => $products,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to filter products.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Customer cannot delete products.
     *
     * DELETE /api/products/{id}
     */
    public function destroy(string $id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized action.',
        ], 403);
    }
}

