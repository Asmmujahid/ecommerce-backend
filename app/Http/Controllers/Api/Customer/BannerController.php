<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Banner;

class BannerController extends Controller
{
    /**
     * Homepage Banners
     */
    public function index()
    {
        $banners = Banner::where('status', true)
            ->orderBy('position', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Banners retrieved successfully',
            'data' => $banners
        ], 200);
    }
}