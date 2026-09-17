<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Brand;

class BrandController extends Controller
{
    /**
     * List Brands
     */
    public function index()
    {
        $brands = Brand::where('status', true)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Brands retrieved successfully',
            'data' => $brands
        ]);
    }

    /**
     * Brand Details
     */
    public function show($id)
    {
        $brand = Brand::where('status', true)
            ->find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $brand
        ]);
    }

    /**
     * Products By Brand
     */
    public function products($id)
    {
        $brand = Brand::with([
            'products' => function ($query) {
                $query->where('status', true);
            }
        ])->where('status', true)
          ->find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'brand' => $brand->name,
            'products' => $brand->products
        ]);
    }
}