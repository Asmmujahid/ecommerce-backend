<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    /**
     * Display all brands
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'brands' => Brand::latest()->get()
        ]);
    }

    /**
     * Store new brand
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'logo' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        $brand = Brand::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'logo' => $request->logo,
            'status' => $request->status ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully',
            'brand' => $brand
        ], 201);
    }

    /**
     * Show single brand
     */
    public function show(string $id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'brand' => $brand
        ]);
    }

    /**
     * Update brand
     */
    public function update(Request $request, string $id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,' . $id,
            'logo' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        $brand->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'logo' => $request->logo,
            'status' => $request->status ?? $brand->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully',
            'brand' => $brand
        ]);
    }

    /**
     * Delete brand
     */
    public function destroy(string $id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Brand deleted successfully'
        ]);
    }
}