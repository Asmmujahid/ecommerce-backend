<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Display customer cart.
     */
public function index()
{


   
    $cart = Cart::firstOrCreate([
        'user_id' => auth()->id(),
    ]);

    $cart->load([
        'items.product',
        'items.productVariant',
    ]);

    return response()->json([
        'success' => true,
        'data' => $cart,
        'total' => $cart->total,
    ]);
}
    /**
     * Add product to cart.
     */
    public function store(Request $request)
    {

    
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);

        
if ((int)$request->quantity > (int)$product->stock) {

    return response()->json([
        "success"=>false,
        "message"=>"Only {$product->stock} item(s) available."
    ],422);

}

        $cart = Cart::firstOrCreate([
            'user_id' => auth()->id(),
        ]);

       $price = $product->discount_price && $product->discount_price > 0
    ? $product->discount_price
    : $product->price;

        $cartItem = CartItem::where([
            'cart_id' => $cart->id,
            'product_id' => $request->product_id,
            'product_variant_id' => $request->product_variant_id,
        ])->first();
       

      if ($cartItem) {

    $availableStock = (int)$product->stock;
    $currentQuantity = (int)$cartItem->quantity;
    $requestedQuantity = (int)$request->quantity;

    $newQuantity = $currentQuantity + $requestedQuantity;

    if ($newQuantity > $availableStock) {

        return response()->json([
            "success"=>false,
            "message"=>"Only {$availableStock} item(s) available.",
            "available_stock"=>$availableStock,
            "current_quantity"=>$currentQuantity,
            "requested_quantity"=>$requestedQuantity,
        ],422);
    }

    $cartItem->update([
        "quantity"=>$newQuantity
    ]);

} else {

            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'product_variant_id' => $request->product_variant_id,
                'quantity' => $request->quantity,
                'price' => $price,
            ]);
        }

        $cartItem->load([
            'product',
            'productVariant',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart successfully.',
            'data' => $cartItem,
        ], 201);
    }

    /**
     * Show single cart item.
     */
    public function show($id)
    {
        $cart = Cart::where('user_id', auth()->id())->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found.',
            ], 404);
        }

        $item = CartItem::with([
            'product',
            'productVariant',
        ])
        ->where('cart_id', $cart->id)
        ->find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    /**
     * Update quantity.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = Cart::where('user_id', auth()->id())->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found.',
            ], 404);
        }

       $item = CartItem::with('product')
    ->where('cart_id', $cart->id)
    ->where('id', $id)
    ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found.',
            ], 404);
        }

        if ($request->quantity > $item->product->stock) {
            return response()->json([
                'success' => false,
                'message' => 'Quantity exceeds available stock.',
            ], 422);
        }

        $item->update([
            'quantity' => $request->quantity,
        ]);

        $item->load([
            'product',
            'productVariant',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cart updated successfully.',
            'data' => $item,
        ]);
    }

    /**
     * Delete item.
     */
    public function destroy($id)
    {
        $cart = Cart::where('user_id', auth()->id())->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found.',
            ], 404);
        }

        $item = CartItem::where('cart_id', $cart->id)
    ->where('id', $id)
    ->first();
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found.',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart successfully.',
            'id' => $id,
        ]);
    }
}