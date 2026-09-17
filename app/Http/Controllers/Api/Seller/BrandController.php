<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Brand;

class BrandController extends Controller
{
    /**
     * Display all active brands.
     */
    public function index()
    {
        $brands = Brand::where('status', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Brands retrieved successfully.',
            'data' => $brands,
        ], 200);
    }

    /**
     * Display a single brand.
     */
    public function show($id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Brand retrieved successfully.',
            'data' => $brand,
        ], 200);
    }
}