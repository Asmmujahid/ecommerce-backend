<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    // Display all banners
    public function index()
    {
        return response()->json([
            'success' => true,
            'banners' => Banner::latest()->get()
        ]);
    }

    // Store new banner
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'image' => 'required|string',
            'link' => 'nullable|string',
            'status' => 'boolean',
            'position' => 'integer'
        ]);

        $banner = Banner::create([
            'title' => $request->title,
            'image' => $request->image,
            'link' => $request->link,
            'status' => $request->status ?? true,
            'position' => $request->position ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully',
            'banner' => $banner
        ], 201);
    }

    // Show single banner
public function show(string $id)
{
    $banner = Banner::find($id);

    if (!$banner) {
        return response()->json([
            'success' => false,
            'message' => 'Banner not found'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'banner' => $banner
    ]);
}

    // Update banner
    public function update(Request $request, string $id)
    {
        $banner = Banner::find($id);

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found'
            ], 404);
        }

        $request->validate([
            'title' => 'nullable|string|max:255',
            'image' => 'nullable|string',
            'link' => 'nullable|string',
            'status' => 'boolean',
            'position' => 'integer'
        ]);

        $banner->update([
            'title' => $request->title ?? $banner->title,
            'image' => $request->image ?? $banner->image,
            'link' => $request->link ?? $banner->link,
            'status' => $request->status ?? $banner->status,
            'position' => $request->position ?? $banner->position,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully',
            'banner' => $banner
        ]);
    }

    // Delete banner
    public function destroy(string $id)
    {
        $banner = Banner::find($id);

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found'
            ], 404);
        }

        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully'
        ]);
    }
}