<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    /**
     * List Categories
     */
    public function index()
    {
        $categories = Category::where('status', true)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully',
            'data' => $categories
        ]);
    }

    /**
     * Category Details
     */
    public function show($id)
    {
        $category = Category::where('status', true)
            ->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Products By Category
     */
    public function products($id)
    {
        $category = Category::with([
            'products' => function ($query) {
                $query->where('status', true);
            }
        ])->where('status', true)
          ->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'category' => $category->name,
            'products' => $category->products
        ]);
    }
}