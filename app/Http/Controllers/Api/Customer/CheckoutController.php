<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    protected OrderService $orderService;

    public function __construct(
        OrderService $orderService
    ) {
        $this->orderService = $orderService;
    }

    /*
    |--------------------------------------------------------------------------
    | PLACE CUSTOMER ORDER
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'payment_method' => [
                'required',
                'string',
                'in:cod,bank_transfer,card,online,wallet',
            ],

            'shipping_address' => [
                'required',
                'string',
                'max:1000',
            ],

            'transaction_id' => [
                'required_if:payment_method,bank_transfer',
                'nullable',
                'string',
                'max:255',
            ],

            'coupon_id' => [
                'nullable',
                'integer',
                'exists:coupons,id',
            ],

            'coupon_code' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | GET CART
        |--------------------------------------------------------------------------
        */

        $cart = Cart::with([
            'items.product',
            'items.productVariant',
        ])
            ->where(
                'user_id',
                $user->id
            )
            ->first();

        if (
            !$cart ||
            $cart->items->isEmpty()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Your cart is empty.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE CART
        |--------------------------------------------------------------------------
        */

        foreach ($cart->items as $item) {

            $product = $item->product;

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'One of the products in your cart no longer exists.',
                ], 422);
            }

            if (!$product->vendor_id) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        "Product '{$product->name}' does not have a vendor assigned.",
                ], 422);
            }

            if (!$product->vendor) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        "The vendor for product '{$product->name}' no longer exists.",
                ], 422);
            }

            if (
                (int) $product->stock <
                (int) $item->quantity
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        "Insufficient stock for {$product->name}.",
                ], 422);
            }

            if ($item->product_variant_id) {

                $variant =
                    $item->productVariant;

                if (!$variant) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Selected product variant no longer exists.',
                    ], 422);
                }

                if (
                    (int) $variant->stock <
                    (int) $item->quantity
                ) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            "Insufficient variant stock for {$product->name}.",
                    ], 422);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE ORDER
        |--------------------------------------------------------------------------
        */

        try {

            $result = DB::transaction(
                function () use (
                    $cart,
                    $validated,
                    $user
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | 1. ORIGINAL CART SUBTOTAL
                    |--------------------------------------------------------------------------
                    |
                    | IMPORTANT:
                    |
                    | This value is NEVER changed because of a coupon.
                    |
                    */

                    $subtotalAmount = 0;

                    foreach (
                        $cart->items
                        as $item
                    ) {

                        $itemPrice =
                            (float) $item->price;

                        $quantity =
                            (int) $item->quantity;

                        $subtotalAmount +=
                            $itemPrice *
                            $quantity;
                    }

                    $subtotalAmount =
                        round(
                            max(
                                0,
                                $subtotalAmount
                            ),
                            2
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | 2. FIND COUPON
                    |--------------------------------------------------------------------------
                    */

                    $coupon = null;

                    if (
                        !empty(
                            $validated['coupon_id']
                        )
                    ) {

                        $coupon =
                            Coupon::find(
                                $validated['coupon_id']
                            );
                    }

                    if (
                        !$coupon &&
                        !empty(
                            $validated['coupon_code']
                        )
                    ) {

                        $coupon =
                            Coupon::where(
                                'code',
                                strtoupper(
                                    trim(
                                        $validated[
                                            'coupon_code'
                                        ]
                                    )
                                )
                            )->first();
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 3. COUPON CALCULATION
                    |--------------------------------------------------------------------------
                    */

                    $discountAmount = 0;

                    $couponId = null;

                    $couponCode = null;

                    if ($coupon) {

                        /*
                        |--------------------------------------------------------------------------
                        | COUPON VALIDITY
                        |--------------------------------------------------------------------------
                        */

                        if (!$coupon->isValid()) {
                            throw new \RuntimeException(
                                'Coupon is invalid or expired.'
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | BUILD CART ITEMS
                        |--------------------------------------------------------------------------
                        |
                        | Same structure used by CouponService.
                        |
                        */

                        $couponItems =
                            $cart->items
                                ->map(
                                    function ($item) {

                                        return [
                                            'product_id' =>
                                                (int)
                                                $item->product_id,

                                            'quantity' =>
                                                (int)
                                                $item->quantity,
                                        ];
                                    }
                                )
                                ->values()
                                ->toArray();

                        /*
                        |--------------------------------------------------------------------------
                        | CALCULATE ELIGIBLE SUBTOTAL
                        |--------------------------------------------------------------------------
                        */

                        $eligibleSubtotal =
                            0;

                        foreach (
                            $cart->items
                            as $item
                        ) {

                            $product =
                                $item->product;

                            /*
                            |--------------------------------------------------------------------------
                            | ONLY PRODUCTS TO WHICH
                            | THE COUPON APPLIES
                            |--------------------------------------------------------------------------
                            */

                            if (
                                !$coupon->appliesToProduct(
                                    $product
                                )
                            ) {
                                continue;
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | USE CART ITEM PRICE
                            |--------------------------------------------------------------------------
                            |
                            | The order subtotal is based on the
                            | actual cart item price.
                            |
                            */

                            $itemPrice =
                                (float)
                                $item->price;

                            $quantity =
                                (int)
                                $item->quantity;

                            $eligibleSubtotal +=
                                $itemPrice *
                                $quantity;
                        }

                        $eligibleSubtotal =
                            round(
                                max(
                                    0,
                                    $eligibleSubtotal
                                ),
                                2
                            );

                        /*
                        |--------------------------------------------------------------------------
                        | COUPON MUST APPLY TO SOMETHING
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $eligibleSubtotal <= 0
                        ) {
                            throw new \RuntimeException(
                                'This coupon is not applicable to the products in your cart.'
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | MINIMUM ELIGIBLE ORDER
                        |--------------------------------------------------------------------------
                        */

                        if (
                            !$coupon->meetsMinimumOrder(
                                $eligibleSubtotal
                            )
                        ) {

                            throw new \RuntimeException(
                                'Minimum eligible order amount of Rs. ' .
                                number_format(
                                    (float)
                                    $coupon->min_order_amount,
                                    2
                                ) .
                                ' is required.'
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | CALCULATE DISCOUNT
                        |--------------------------------------------------------------------------
                        |
                        | IMPORTANT:
                        |
                        | Discount is calculated from the
                        | ORIGINAL eligible subtotal.
                        |
                        | It is NEVER calculated from a previous
                        | discounted amount.
                        |
                        */

                        $discountAmount =
                            $coupon->calculateDiscount(
                                $eligibleSubtotal
                            );

                        $discountAmount =
                            min(
                                max(
                                    0,
                                    $discountAmount
                                ),
                                $subtotalAmount
                            );

                        $discountAmount =
                            round(
                                $discountAmount,
                                2
                            );

                        $couponId =
                            $coupon->id;

                        $couponCode =
                            $coupon->code;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 4. FINAL ORDER TOTAL
                    |--------------------------------------------------------------------------
                    |
                    | ALWAYS:
                    |
                    | original subtotal - current coupon discount
                    |
                    */

                    $totalAmount =
                        round(
                            max(
                                0,
                                $subtotalAmount -
                                    $discountAmount
                            ),
                            2
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | 5. ORDER NUMBER
                    |--------------------------------------------------------------------------
                    */

                    do {

                        $orderNumber =
                            'ORD-' .
                            now()->format(
                                'YmdHis'
                            ) .
                            '-' .
                            strtoupper(
                                Str::random(6)
                            );

                    } while (
                        Order::where(
                            'order_number',
                            $orderNumber
                        )->exists()
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | 6. CREATE ORDER
                    |--------------------------------------------------------------------------
                    */

                    $order = Order::create([
                        'user_id' =>
                            $user->id,

                        'order_number' =>
                            $orderNumber,

                        'subtotal_amount' =>
                            $subtotalAmount,

                        'coupon_id' =>
                            $couponId,

                        'coupon_code' =>
                            $couponCode,

                        'discount_amount' =>
                            $discountAmount,

                        'total_amount' =>
                            $totalAmount,

                        'status' =>
                            'pending',

                        'payment_status' =>
                            'pending',

                        'payment_method' =>
                            $validated[
                                'payment_method'
                            ],

                        'shipping_address' =>
                            $validated[
                                'shipping_address'
                            ],
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | 7. CREATE ORDER ITEMS
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $cart->items
                        as $item
                    ) {

                        $product =
                            $item->product;

                        $vendorId =
                            $product->vendor_id;

                        if (!$vendorId) {
                            throw new \RuntimeException(
                                "Product '{$product->name}' does not have a vendor assigned."
                            );
                        }

                        $itemPrice =
                            (float) $item->price;

                        $quantity =
                            (int) $item->quantity;

                        $itemTotal =
                            round(
                                $itemPrice *
                                    $quantity,
                                2
                            );

                        OrderItem::create([
                            'order_id' =>
                                $order->id,

                            'product_id' =>
                                $item->product_id,

                            'vendor_id' =>
                                $vendorId,

                            'product_variant_id' =>
                                $item->product_variant_id,

                            'quantity' =>
                                $quantity,

                            'price' =>
                                $itemPrice,

                            'total' =>
                                $itemTotal,
                        ]);

                        /*
                        |--------------------------------------------------------------------------
                        | DECREASE PRODUCT STOCK
                        |--------------------------------------------------------------------------
                        */

                        Product::where(
                            'id',
                            $item->product_id
                        )->decrement(
                            'stock',
                            $quantity
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | DECREASE VARIANT STOCK
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $item->product_variant_id
                        ) {

                            ProductVariant::where(
                                'id',
                                $item->product_variant_id
                            )->decrement(
                                'stock',
                                $quantity
                            );
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 8. COUPON USAGE
                    |--------------------------------------------------------------------------
                    */

                    if ($coupon) {
                        $coupon->increment(
                            'used_count'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 9. PAYMENT
                    |--------------------------------------------------------------------------
                    */

                    $payment = Payment::create([
                        'user_id' =>
                            $user->id,

                        'order_id' =>
                            $order->id,

                        'transaction_id' =>
                            $validated[
                                'transaction_id'
                            ] ?? null,

                        'amount' =>
                            $totalAmount,

                        'payment_method' =>
                            $validated[
                                'payment_method'
                            ],

                        'status' =>
                            'pending',

                        'paid_at' =>
                            null,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | 10. CLEAR CART
                    |--------------------------------------------------------------------------
                    */

                    $cart->items()->delete();

                    /*
                    |--------------------------------------------------------------------------
                    | 11. LOAD RELATIONSHIPS
                    |--------------------------------------------------------------------------
                    */

                    $order->load([
                        'user',
                        'coupon',
                        'items.product',
                        'items.productVariant',
                        'items.vendor',
                        'payment',
                    ]);

                    $payment->load([
                        'order',
                    ]);

                    return [
                        'order' =>
                            $order,

                        'payment' =>
                            $payment,
                    ];
                }
            );

            /*
            |--------------------------------------------------------------------------
            | NOTIFICATIONS
            |--------------------------------------------------------------------------
            */

            try {

                $this->orderService
                    ->sendOrderPlacedNotifications(
                        $result['order']
                    );

            } catch (\Throwable $notificationError) {

                Log::error(
                    'Order notification process failed.',
                    [
                        'order_id' =>
                            $result['order']->id,

                        'user_id' =>
                            $user->id,

                        'error' =>
                            $notificationError
                                ->getMessage(),

                        'file' =>
                            $notificationError
                                ->getFile(),

                        'line' =>
                            $notificationError
                                ->getLine(),
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'message' =>
                    'Order placed successfully.',

                'data' => [
                    'order' =>
                        $result['order'],

                    'payment' =>
                        $result['payment'],
                ],
            ], 201);

        } catch (\Throwable $e) {

            Log::error(
                'Checkout failed.',
                [
                    'user_id' =>
                        $user->id,

                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to place order.',

                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,

            ], $e instanceof \RuntimeException
                ? 422
                : 500);
        }
    }
}