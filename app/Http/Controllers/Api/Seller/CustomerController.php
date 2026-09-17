<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\User;

class CustomerController extends Controller
{
    public function index()
    {
        $vendor = auth()->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor account not found.'
            ],404);
        }

        $customers = User::role('customer')
            ->whereHas('orders', function ($order) use ($vendor) {

                $order->whereHas('items.product', function ($product) use ($vendor) {

                    $product->where('vendor_id',$vendor->id);

                });

            })
            ->withCount([

                'orders as total_orders' => function ($order) use ($vendor) {

                    $order->whereHas('items.product', function ($product) use ($vendor) {

                        $product->where('vendor_id',$vendor->id);

                    });

                }

            ])
            ->latest()
            ->get();

        return response()->json([
            'success'=>true,
            'data'=>$customers
        ]);
    }

    public function show($id)
    {
        $vendor = auth()->user()->vendor;

        if (!$vendor) {

            return response()->json([
                'success'=>false,
                'message'=>'Vendor account not found.'
            ],404);

        }

        $customer = User::role('customer')

            ->whereHas('orders', function ($order) use ($vendor) {

                $order->whereHas('items.product', function ($product) use ($vendor) {

                    $product->where('vendor_id',$vendor->id);

                });

            })

            ->with([

                'addresses',

                'orders.items.product',

                'orders.items.productVariant'

            ])

            ->find($id);

        if(!$customer){

            return response()->json([

                'success'=>false,

                'message'=>'Customer not found.'

            ],404);

        }

        return response()->json([

            'success'=>true,

            'data'=>$customer

        ]);
    }
}