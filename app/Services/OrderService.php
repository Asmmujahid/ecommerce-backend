<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VendorEarning;

use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusUpdatedNotification;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    /*
    |--------------------------------------------------------------------------
    | ALLOWED ORDER STATUSES
    |--------------------------------------------------------------------------
    */

    protected array $allowedStatuses = [
        'pending',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
    ];

    /*
    |--------------------------------------------------------------------------
    | ALLOWED PAYMENT STATUSES
    |--------------------------------------------------------------------------
    */

    protected array $allowedPaymentStatuses = [
        'pending',
        'paid',
        'failed',
        'refunded',
    ];

    /*
    |--------------------------------------------------------------------------
    | CREATE ORDER
    |--------------------------------------------------------------------------
    */

    public function createOrder(array $data): Order
    {
        $order = DB::transaction(function () use ($data) {

            if (
                empty($data['items']) ||
                !is_array($data['items'])
            ) {
                throw new \RuntimeException(
                    'Order must contain at least one item.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | USER
            |--------------------------------------------------------------------------
            */

            $userId = $data['user_id'] ?? Auth::id();

            if (!$userId) {
                throw new \RuntimeException(
                    'A valid customer is required to create an order.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | ORDER STATUS
            |--------------------------------------------------------------------------
            */

            $orderStatus = strtolower(
                trim(
                    (string) ($data['status'] ?? 'pending')
                )
            );

            $paymentStatus = strtolower(
                trim(
                    (string) ($data['payment_status'] ?? 'pending')
                )
            );

            $this->validateOrderStatus($orderStatus);

            $this->validatePaymentStatus($paymentStatus);

            /*
            |--------------------------------------------------------------------------
            | PAYMENT AUTHORITY
            |--------------------------------------------------------------------------
            |
            | Paid orders must be processed through PaymentService.
            |
            */

            if ($paymentStatus === 'paid') {
                throw new \RuntimeException(
                    'A paid order must be processed through PaymentService.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | PREPARE ITEMS
            |--------------------------------------------------------------------------
            */

            $preparedItems = [];

            $productQuantities = [];

            $variantQuantities = [];

            foreach ($data['items'] as $item) {

                if (
                    empty($item['product_id']) ||
                    empty($item['quantity'])
                ) {
                    throw new \RuntimeException(
                        'Each order item must contain product_id and quantity.'
                    );
                }

                $productId = (int) $item['product_id'];

                $quantity = (int) $item['quantity'];

                if ($quantity <= 0) {
                    throw new \RuntimeException(
                        'Order quantity must be greater than zero.'
                    );
                }

                $variantId = !empty($item['product_variant_id'])
                    ? (int) $item['product_variant_id']
                    : null;

                $productQuantities[$productId] =
                    ($productQuantities[$productId] ?? 0)
                    + $quantity;

                if ($variantId) {
                    $variantQuantities[$variantId] =
                        ($variantQuantities[$variantId] ?? 0)
                        + $quantity;
                }

                $preparedItems[] = [
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | LOAD + LOCK PRODUCTS
            |--------------------------------------------------------------------------
            */

            $products = Product::whereIn(
                'id',
                array_keys($productQuantities)
            )
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if (
                $products->count() !==
                count($productQuantities)
            ) {
                throw new \RuntimeException(
                    'One or more selected products were not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATE PRODUCTS
            |--------------------------------------------------------------------------
            */

            foreach (
                $productQuantities as $productId => $requiredQuantity
            ) {

                /** @var Product $product */
                $product = $products->get($productId);

                /*
                |--------------------------------------------------------------------------
                | VENDOR STORE REQUIRED
                |--------------------------------------------------------------------------
                */

                if (!$product->vendor_id) {
                    throw new \RuntimeException(
                        "Product {$product->name} does not have a vendor assigned."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | OWNER TYPE
                |--------------------------------------------------------------------------
                */

                $ownerType = $this->normalizeOwnerType(
                    $product->owner_type
                );

                if (!$ownerType) {
                    throw new \RuntimeException(
                        "Product {$product->name} has an invalid owner type."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | STOCK
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $product->stock <
                    (int) $requiredQuantity
                ) {
                    throw new \RuntimeException(
                        "Insufficient stock for {$product->name}. " .
                        "Available: {$product->stock}, " .
                        "Requested: {$requiredQuantity}."
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | LOAD + LOCK VARIANTS
            |--------------------------------------------------------------------------
            */

            $variants = collect();

            if (!empty($variantQuantities)) {

                $variants = ProductVariant::whereIn(
                    'id',
                    array_keys($variantQuantities)
                )
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if (
                    $variants->count() !==
                    count($variantQuantities)
                ) {
                    throw new \RuntimeException(
                        'One or more selected product variants were not found.'
                    );
                }

                foreach (
                    $variantQuantities as $variantId => $requiredQuantity
                ) {

                    /** @var ProductVariant $variant */
                    $variant = $variants->get($variantId);

                    if (
                        (int) $variant->stock <
                        (int) $requiredQuantity
                    ) {
                        throw new \RuntimeException(
                            "Insufficient variant stock. " .
                            "Available: {$variant->stock}, " .
                            "Requested: {$requiredQuantity}."
                        );
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATE VARIANT -> PRODUCT
            |--------------------------------------------------------------------------
            */

            foreach ($preparedItems as $preparedItem) {

                if (!$preparedItem['product_variant_id']) {
                    continue;
                }

                $product = $products->get(
                    $preparedItem['product_id']
                );

                $variant = $variants->get(
                    $preparedItem['product_variant_id']
                );

                if (
                    !$variant ||
                    (int) $variant->product_id !==
                    (int) $product->id
                ) {
                    throw new \RuntimeException(
                        'Selected variant does not belong to the selected product.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | ORIGINAL ORDER SUBTOTAL
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | This is the ORIGINAL subtotal.
            |
            | Coupon calculations NEVER modify this value.
            |
            */

            $subtotalAmount = 0;

            foreach ($preparedItems as $preparedItem) {

                /** @var Product $product */
                $product = $products->get(
                    $preparedItem['product_id']
                );

                $price = $this->getProductPrice(
                    $product
                );

                $quantity = (int) $preparedItem['quantity'];

                $subtotalAmount +=
                    $price * $quantity;
            }

            $subtotalAmount = round(
                max(0, $subtotalAmount),
                2
            );

            /*
            |--------------------------------------------------------------------------
            | COUPON VARIABLES
            |--------------------------------------------------------------------------
            */

            $coupon = null;

            $couponId = null;

            $couponCode = null;

            $discountAmount = 0;

            $eligibleSubtotal = 0;

            /*
            |--------------------------------------------------------------------------
            | GET COUPON
            |--------------------------------------------------------------------------
            |
            | Only ONE coupon is allowed.
            |
            | coupon_id and coupon_code are two ways of identifying
            | the SAME coupon. They are NOT two coupons.
            |
            */

            $couponIdInput = !empty($data['coupon_id'])
                ? (int) $data['coupon_id']
                : null;

            $couponCodeInput = !empty($data['coupon_code'])
                ? strtoupper(
                    trim(
                        (string) $data['coupon_code']
                    )
                )
                : null;

            /*
            |--------------------------------------------------------------------------
            | COUPON BY ID
            |--------------------------------------------------------------------------
            */

            if ($couponIdInput !== null) {

                $coupon = Coupon::lockForUpdate()
                    ->find($couponIdInput);

                if (!$coupon) {
                    throw new \RuntimeException(
                        'The selected coupon was not found.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | COUPON BY CODE
            |--------------------------------------------------------------------------
            */

            if ($couponCodeInput !== null) {

                $couponByCode = Coupon::lockForUpdate()
                    ->where(
                        'code',
                        $couponCodeInput
                    )
                    ->first();

                if (!$couponByCode) {
                    throw new \RuntimeException(
                        'The selected coupon code was not found.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | ID + CODE MUST MATCH
                |--------------------------------------------------------------------------
                |
                | Prevents frontend from sending:
                |
                | coupon_id = A
                | coupon_code = B
                |
                */

                if (
                    $coupon &&
                    (int) $coupon->id !==
                    (int) $couponByCode->id
                ) {
                    throw new \RuntimeException(
                        'Coupon ID and coupon code do not match.'
                    );
                }

                $coupon = $couponByCode;
            }

            /*
            |--------------------------------------------------------------------------
            | APPLY ONE COUPON ONLY
            |--------------------------------------------------------------------------
            */

            if ($coupon) {

                /*
                |--------------------------------------------------------------------------
                | BASIC VALIDITY
                |--------------------------------------------------------------------------
                */

                if (!$coupon->isValid()) {
                    throw new \RuntimeException(
                        'Coupon is invalid, inactive, expired, or its usage limit has been reached.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | ELIGIBLE SUBTOTAL
                |--------------------------------------------------------------------------
                |
                | This is calculated from ORIGINAL PRODUCT PRICES.
                |
                | It is NOT calculated from:
                |
                | subtotal - previous discount
                |
                */

                $eligibleSubtotal =
                    $this->calculateCouponEligibleSubtotal(
                        $coupon,
                        $preparedItems,
                        $products
                    );

                /*
                |--------------------------------------------------------------------------
                | NO ELIGIBLE PRODUCTS
                |--------------------------------------------------------------------------
                */

                if ($eligibleSubtotal <= 0) {
                    throw new \RuntimeException(
                        $this->getCouponEligibilityMessage(
                            $coupon
                        )
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | MINIMUM ELIGIBLE ORDER AMOUNT
                |--------------------------------------------------------------------------
                */

                if (
                    $coupon->min_order_amount !== null &&
                    $eligibleSubtotal <
                    (float) $coupon->min_order_amount
                ) {
                    throw new \RuntimeException(
                        'Minimum eligible order amount of Rs. ' .
                        number_format(
                            (float) $coupon->min_order_amount,
                            2
                        ) .
                        ' is required.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | CALCULATE ONE DISCOUNT
                |--------------------------------------------------------------------------
                */

                $discountAmount =
                    $this->calculateCouponDiscount(
                        $coupon,
                        $eligibleSubtotal
                    );

                /*
                |--------------------------------------------------------------------------
                | FINAL COUPON INFORMATION
                |--------------------------------------------------------------------------
                */

                $couponId = (int) $coupon->id;

                $couponCode = strtoupper(
                    trim(
                        (string) $coupon->code
                    )
                );
            }

            /*
            |--------------------------------------------------------------------------
            | FINAL ORDER TOTAL
            |--------------------------------------------------------------------------
            |
            | THIS IS THE ONLY TOTAL FORMULA:
            |
            | original subtotal - current coupon discount
            |
            | Example:
            |
            | 10,000 - 1,000 = 9,000
            |
            | Never:
            |
            | 10,000 - 1,000 - another discount
            |
            */

            $totalAmount = round(
                max(
                    0,
                    $subtotalAmount -
                    $discountAmount
                ),
                2
            );

            /*
            |--------------------------------------------------------------------------
            | CREATE ORDER
            |--------------------------------------------------------------------------
            */

            $order = Order::create([

                'user_id' =>
                    $userId,

                'order_number' =>
                    'ORD-' .
                    now()->format('YmdHis') .
                    '-' .
                    random_int(
                        1000,
                        9999
                    ),

                /*
                | ORIGINAL SUBTOTAL
                */
                'subtotal_amount' =>
                    $subtotalAmount,

                /*
                | ONE COUPON ONLY
                */
                'coupon_id' =>
                    $couponId,

                'coupon_code' =>
                    $couponCode,

                /*
                | ONE DISCOUNT ONLY
                */
                'discount_amount' =>
                    $discountAmount,

                /*
                | ORIGINAL SUBTOTAL - ONE DISCOUNT
                */
                'total_amount' =>
                    $totalAmount,

                'status' =>
                    $orderStatus,

                'payment_status' =>
                    $paymentStatus,

                'payment_method' =>
                    $data['payment_method'] ??
                    'cod',

                'shipping_address' =>
                    $data['shipping_address'] ??
                    null,

                'tracking_number' =>
                    $data['tracking_number'] ??
                    null,

                'admin_note' =>
                    $data['admin_note'] ??
                    null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | CREATE ORDER ITEMS
            |--------------------------------------------------------------------------
            */

            foreach ($preparedItems as $preparedItem) {

                /** @var Product $product */
                $product = $products->get(
                    $preparedItem['product_id']
                );

                $ownerType =
                    $this->normalizeOwnerType(
                        $product->owner_type
                    );

                $price =
                    $this->getProductPrice(
                        $product
                    );

                $quantity =
                    (int) $preparedItem['quantity'];

                $itemTotal = round(
                    $price * $quantity,
                    2
                );

                OrderItem::create([

                    'order_id' =>
                        $order->id,

                    'product_id' =>
                        $product->id,

                    'vendor_id' =>
                        $product->vendor_id,

                    'product_owner_type' =>
                        $ownerType,

                    'product_variant_id' =>
                        $preparedItem['product_variant_id'],

                    'quantity' =>
                        $quantity,

                    'price' =>
                        $price,

                    'total' =>
                        $itemTotal,
                ]);

                /*
                |--------------------------------------------------------------------------
                | DECREASE PRODUCT STOCK
                |--------------------------------------------------------------------------
                */

                $product->decrement(
                    'stock',
                    $quantity
                );

                /*
                |--------------------------------------------------------------------------
                | DECREASE VARIANT STOCK
                |--------------------------------------------------------------------------
                */

                if (
                    $preparedItem['product_variant_id']
                ) {

                    $variant =
                        $variants->get(
                            $preparedItem['product_variant_id']
                        );

                    $variant->decrement(
                        'stock',
                        $quantity
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | COUPON USAGE
            |--------------------------------------------------------------------------
            |
            | One order = one coupon usage.
            |
            */

            if ($coupon) {

                $coupon->increment(
                    'used_count'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | LOAD ORDER
            |--------------------------------------------------------------------------
            */

            return $this->loadOrder(
                $order
            );
        });

        /*
        |--------------------------------------------------------------------------
        | NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

        $this->sendOrderPlacedNotifications(
            $order
        );

        return $order;
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT PRICE
    |--------------------------------------------------------------------------
    */

    protected function getProductPrice(
        Product $product
    ): float {

        $price = $product->discount_price
            ?? $product->price;

        return round(
            max(
                0,
                (float) $price
            ),
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE OWNER TYPE
    |--------------------------------------------------------------------------
    */

    protected function normalizeOwnerType(
        mixed $ownerType
    ): ?string {

        $ownerType = strtolower(
            trim(
                (string) $ownerType
            )
        );

        return in_array(
            $ownerType,
            [
                'admin',
                'vendor',
            ],
            true
        )
            ? $ownerType
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK COUPON -> PRODUCT ELIGIBILITY
    |--------------------------------------------------------------------------
    */

    protected function couponAppliesToProduct(
        Coupon $coupon,
        Product $product
    ): bool {

        /*
        |--------------------------------------------------------------------------
        | VENDOR COUPON
        |--------------------------------------------------------------------------
        |
        | Vendor coupon applies ONLY when:
        |
        | owner_type = vendor
        | AND
        | product.vendor_id = coupon.vendor_id
        |
        */

        if ($coupon->vendor_id !== null) {

            return
                $this->normalizeOwnerType(
                    $product->owner_type
                ) === 'vendor'
                &&
                (int) $product->vendor_id ===
                (int) $coupon->vendor_id;
        }

        /*
        |--------------------------------------------------------------------------
        | ADMIN COUPON
        |--------------------------------------------------------------------------
        */

        $scope = strtolower(
            trim(
                (string) $coupon->admin_coupon_scope
            )
        );

        /*
        |--------------------------------------------------------------------------
        | ADMIN ONLY
        |--------------------------------------------------------------------------
        */

        if ($scope === 'admin_only') {

            return
                $this->normalizeOwnerType(
                    $product->owner_type
                ) === 'admin';
        }

        /*
        |--------------------------------------------------------------------------
        | ALL PRODUCTS
        |--------------------------------------------------------------------------
        */

        if ($scope === 'all_products') {
            return true;
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | CALCULATE COUPON ELIGIBLE SUBTOTAL
    |--------------------------------------------------------------------------
    */

    protected function calculateCouponEligibleSubtotal(
        Coupon $coupon,
        array $preparedItems,
        $products
    ): float {

        $eligibleSubtotal = 0;

        foreach ($preparedItems as $preparedItem) {

            /** @var Product|null $product */
            $product = $products->get(
                $preparedItem['product_id']
            );

            if (!$product) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | ELIGIBILITY
            |--------------------------------------------------------------------------
            */

            if (
                !$this->couponAppliesToProduct(
                    $coupon,
                    $product
                )
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | ORIGINAL PRODUCT PRICE
            |--------------------------------------------------------------------------
            */

            $price =
                $this->getProductPrice(
                    $product
                );

            $quantity =
                (int) $preparedItem['quantity'];

            $eligibleSubtotal +=
                $price * $quantity;
        }

        return round(
            max(
                0,
                $eligibleSubtotal
            ),
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CALCULATE COUPON DISCOUNT
    |--------------------------------------------------------------------------
    */

    protected function calculateCouponDiscount(
        Coupon $coupon,
        float $eligibleSubtotal
    ): float {

        $eligibleSubtotal = round(
            max(
                0,
                $eligibleSubtotal
            ),
            2
        );

        if ($eligibleSubtotal <= 0) {
            return 0;
        }

        /*
        |--------------------------------------------------------------------------
        | PERCENTAGE
        |--------------------------------------------------------------------------
        */

        if ($coupon->type === 'percentage') {

            $discountAmount =
                (
                    $eligibleSubtotal *
                    (float) $coupon->value
                ) / 100;
        }

        /*
        |--------------------------------------------------------------------------
        | FIXED
        |--------------------------------------------------------------------------
        */

        elseif ($coupon->type === 'fixed') {

            $discountAmount =
                (float) $coupon->value;
        }

        /*
        |--------------------------------------------------------------------------
        | INVALID TYPE
        |--------------------------------------------------------------------------
        */

        else {

            throw new \RuntimeException(
                'Invalid coupon type.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | MAX DISCOUNT
        |--------------------------------------------------------------------------
        */

        if (
            $coupon->max_discount !== null &&
            (float) $coupon->max_discount > 0
        ) {

            $discountAmount = min(
                $discountAmount,
                (float) $coupon->max_discount
            );
        }

        /*
        |--------------------------------------------------------------------------
        | NEVER DISCOUNT MORE THAN ELIGIBLE SUBTOTAL
        |--------------------------------------------------------------------------
        */

        $discountAmount = min(
            $discountAmount,
            $eligibleSubtotal
        );

        return round(
            max(
                0,
                $discountAmount
            ),
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | COUPON ELIGIBILITY MESSAGE
    |--------------------------------------------------------------------------
    */

    protected function getCouponEligibilityMessage(
        Coupon $coupon
    ): string {

        /*
        |--------------------------------------------------------------------------
        | VENDOR COUPON
        |--------------------------------------------------------------------------
        */

        if ($coupon->vendor_id !== null) {

            return
                'This coupon is only valid for products owned by the selected vendor.';
        }

        /*
        |--------------------------------------------------------------------------
        | ADMIN COUPON
        |--------------------------------------------------------------------------
        */

        $scope = strtolower(
            trim(
                (string) $coupon->admin_coupon_scope
            )
        );

        if ($scope === 'admin_only') {

            return
                'This coupon is only valid for admin-owned products.';
        }

        if ($scope === 'all_products') {

            return
                'This coupon cannot be applied to the selected products.';
        }

        return
            'This coupon is not applicable to the selected products.';
    }

    /*
    |--------------------------------------------------------------------------
    | SEND ORDER PLACED NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    public function sendOrderPlacedNotifications(
        Order $order
    ): void {

        $order->loadMissing([
            'user',
            'items.product',
            'items.productVariant',
        ]);

        $this->notifyCustomerOrderPlaced(
            $order
        );

        $this->notifyAdminsOrderPlaced(
            $order
        );

        $this->notifySellersOrderPlaced(
            $order
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER ORDER PLACED
    |--------------------------------------------------------------------------
    */

    protected function notifyCustomerOrderPlaced(
        Order $order
    ): void {

        if (!$order->user) {

            Log::warning(
                'Customer not found for order notification.',
                [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                ]
            );

            return;
        }

        try {

            $order->user->notify(
                new OrderPlacedNotification(
                    $order
                )
            );

        } catch (\Throwable $e) {

            Log::error(
                'Customer order placed notification failed.',
                [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN ORDER PLACED
    |--------------------------------------------------------------------------
    */

    protected function notifyAdminsOrderPlaced(
        Order $order
    ): void {

        try {

            $admins = User::role('admin')->get();

        } catch (\Throwable $e) {

            Log::error(
                'Failed to retrieve admin users.',
                [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]
            );

            return;
        }

        foreach ($admins as $admin) {

            try {

                $admin->notify(
                    new OrderPlacedNotification(
                        $order
                    )
                );

            } catch (\Throwable $e) {

                Log::error(
                    'Admin order placed notification failed.',
                    [
                        'order_id' => $order->id,
                        'admin_id' => $admin->id,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SELLER ORDER PLACED
    |--------------------------------------------------------------------------
    */

    protected function notifySellersOrderPlaced(
        Order $order
    ): void {

        $vendorIds = $order->items
            ->map(
                fn ($item) =>
                    $item->vendor_id
            )
            ->filter()
            ->unique()
            ->values();

        if ($vendorIds->isEmpty()) {

            Log::warning(
                'No vendors found for order notification.',
                [
                    'order_id' => $order->id,
                ]
            );

            return;
        }

        try {

            $sellers = User::role('seller')
                ->whereHas(
                    'vendor',
                    function ($query) use ($vendorIds) {

                        $query->whereIn(
                            'id',
                            $vendorIds
                        );
                    }
                )
                ->get();

        } catch (\Throwable $e) {

            Log::error(
                'Failed to retrieve sellers.',
                [
                    'order_id' =>
                        $order->id,

                    'vendor_ids' =>
                        $vendorIds->toArray(),

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return;
        }

        foreach ($sellers as $seller) {

            try {

                $seller->notify(
                    new OrderPlacedNotification(
                        $order
                    )
                );

            } catch (\Throwable $e) {

                Log::error(
                    'Seller order placed notification failed.',
                    [
                        'order_id' =>
                            $order->id,

                        'seller_id' =>
                            $seller->id,

                        'error' =>
                            $e->getMessage(),
                    ]
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ORDER STATUS
    |--------------------------------------------------------------------------
    */

    public function updateStatus(
        Order $order,
        string $status
    ): Order {

        $status = strtolower(
            trim($status)
        );

        $this->validateOrderStatus(
            $status
        );

        $oldStatus = strtolower(
            (string) $order->status
        );

        if ($oldStatus === $status) {

            return $this->loadOrder(
                $order
            );
        }

        $order = DB::transaction(
            function () use (
                $order,
                $status,
                $oldStatus
            ) {

                $order->load([
                    'items.product',
                    'items.productVariant',
                ]);

                if (
                    $status === 'cancelled' &&
                    $oldStatus !== 'cancelled'
                ) {

                    $this->restoreOrderStock(
                        $order
                    );

                    $this->removePendingVendorEarnings(
                        $order
                    );
                }

                $order->update([
                    'status' => $status,
                ]);

                return $this->loadOrder(
                    $order
                );
            }
        );

        $this->sendCustomerOrderStatusNotification(
            $order,
            $oldStatus,
            $status
        );

        if (
            $oldStatus !== 'cancelled' &&
            $status === 'cancelled'
        ) {

            $this->notifyAdminsAndSellersOrderCancelled(
                $order
            );
        }

        return $order;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PAYMENT STATUS
    |--------------------------------------------------------------------------
    */

    public function updatePaymentStatus(
        Order $order,
        string $status
    ): Order {

        $status = strtolower(
            trim($status)
        );

        $this->validatePaymentStatus(
            $status
        );

        $oldStatus = strtolower(
            (string) $order->payment_status
        );

        if ($oldStatus === $status) {

            return $this->loadOrder(
                $order
            );
        }

        if ($status === 'paid') {

            /** @var PaymentService $paymentService */
            $paymentService = app(
                PaymentService::class
            );

            $payment = $order->payment;

            if (!$payment) {

                throw new \RuntimeException(
                    'Payment record not found for this order.'
                );
            }

            $paymentService->markAsPaid(
                $payment
            );

            $order = $this->loadOrder(
                $order
            );

            $this->sendCustomerPaymentStatusNotification(
                $order,
                $oldStatus,
                'paid'
            );

            return $order;
        }

        $order = DB::transaction(
            function () use (
                $order,
                $status
            ) {

                $order->update([
                    'payment_status' => $status,
                ]);

                if (
                    in_array(
                        $status,
                        [
                            'failed',
                            'refunded',
                        ],
                        true
                    )
                ) {

                    $this->removePendingVendorEarnings(
                        $order
                    );
                }

                return $this->loadOrder(
                    $order
                );
            }
        );

        $this->sendCustomerPaymentStatusNotification(
            $order,
            $oldStatus,
            $status
        );

        return $order;
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE PENDING VENDOR EARNINGS
    |--------------------------------------------------------------------------
    */

    protected function removePendingVendorEarnings(
        Order $order
    ): void {

        VendorEarning::where(
            'order_id',
            $order->id
        )
            ->where(
                'status',
                'pending'
            )
            ->update([
                'status' => 'reversed',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE COMPLETE ORDER
    |--------------------------------------------------------------------------
    */

    public function updateOrder(
        Order $order,
        array $data
    ): Order {

        $oldStatus = strtolower(
            (string) $order->status
        );

        $oldPaymentStatus = strtolower(
            (string) $order->payment_status
        );

        $oldTrackingNumber =
            $order->tracking_number;

        $oldAdminNote =
            $order->admin_note;

        $newStatus =
            $oldStatus;

        $newPaymentStatus =
            $oldPaymentStatus;

        $newTrackingNumber =
            $oldTrackingNumber;

        $newAdminNote =
            $oldAdminNote;

        if (
            array_key_exists(
                'status',
                $data
            )
        ) {

            $newStatus = strtolower(
                trim(
                    (string) $data['status']
                )
            );

            $this->validateOrderStatus(
                $newStatus
            );
        }

        if (
            array_key_exists(
                'payment_status',
                $data
            )
        ) {

            $newPaymentStatus = strtolower(
                trim(
                    (string) $data['payment_status']
                )
            );

            $this->validatePaymentStatus(
                $newPaymentStatus
            );
        }

        if (
            array_key_exists(
                'tracking_number',
                $data
            )
        ) {

            $newTrackingNumber =
                $data['tracking_number'] !== null
                    ? trim(
                        (string) $data['tracking_number']
                    )
                    : null;
        }

        if (
            array_key_exists(
                'admin_note',
                $data
            )
        ) {

            $newAdminNote =
                $data['admin_note'] !== null
                    ? trim(
                        (string) $data['admin_note']
                    )
                    : null;
        }

        $statusChanged =
            $oldStatus !==
            $newStatus;

        $paymentChanged =
            $oldPaymentStatus !==
            $newPaymentStatus;

        $trackingChanged =
            $oldTrackingNumber !==
            $newTrackingNumber;

        $adminNoteChanged =
            $oldAdminNote !==
            $newAdminNote;

        if (
            !$statusChanged &&
            !$paymentChanged &&
            !$trackingChanged &&
            !$adminNoteChanged
        ) {

            return $this->loadOrder(
                $order
            );
        }

        if (
            $paymentChanged &&
            $newPaymentStatus === 'paid'
        ) {

            /** @var PaymentService $paymentService */
            $paymentService = app(
                PaymentService::class
            );

            $payment = $order->payment;

            if (!$payment) {

                throw new \RuntimeException(
                    'Payment record not found for this order.'
                );
            }

            $paymentService->markAsPaid(
                $payment
            );

            $newPaymentStatus = 'paid';

            $paymentChanged = false;
        }

        $order = DB::transaction(
            function () use (
                $order,
                $newStatus,
                $newPaymentStatus,
                $newTrackingNumber,
                $newAdminNote,
                $oldStatus,
                $paymentChanged
            ) {

                $order->load([
                    'items.product',
                    'items.productVariant',
                ]);

                if (
                    $newStatus === 'cancelled' &&
                    $oldStatus !== 'cancelled'
                ) {

                    $this->restoreOrderStock(
                        $order
                    );

                    $this->removePendingVendorEarnings(
                        $order
                    );
                }

                if (
                    $paymentChanged &&
                    in_array(
                        $newPaymentStatus,
                        [
                            'failed',
                            'refunded',
                        ],
                        true
                    )
                ) {

                    $this->removePendingVendorEarnings(
                        $order
                    );
                }

                $order->update([

                    'status' =>
                        $newStatus,

                    'payment_status' =>
                        $newPaymentStatus,

                    'tracking_number' =>
                        $newTrackingNumber,

                    'admin_note' =>
                        $newAdminNote,
                ]);

                return $this->loadOrder(
                    $order
                );
            }
        );

        $this->sendCombinedOrderUpdateNotification(
            $order,
            $oldStatus,
            $oldPaymentStatus,
            $oldTrackingNumber,
            $oldAdminNote
        );

        if (
            $oldStatus !== 'cancelled' &&
            $order->status === 'cancelled'
        ) {

            $this->notifyAdminsAndSellersOrderCancelled(
                $order
            );
        }

        return $order;
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER ORDER STATUS NOTIFICATION
    |--------------------------------------------------------------------------
    */

    protected function sendCustomerOrderStatusNotification(
        Order $order,
        string $oldStatus,
        string $newStatus
    ): void {

        $customer =
            $order->user;

        if (!$customer) {
            return;
        }

        try {

            if ($newStatus === 'cancelled') {

                $customer->notify(
                    new OrderCancelledNotification(
                        $order
                    )
                );

                return;
            }

            if ($newStatus === 'delivered') {

                $customer->notify(
                    new OrderDeliveredNotification(
                        $order
                    )
                );

                return;
            }

            $customer->notify(
                new OrderStatusUpdatedNotification(
                    order: $order,
                    oldStatus: $oldStatus
                )
            );

        } catch (\Throwable $e) {

            Log::error(
                'Customer status notification failed.',
                [
                    'order_id' =>
                        $order->id,

                    'user_id' =>
                        $order->user_id,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER PAYMENT STATUS NOTIFICATION
    |--------------------------------------------------------------------------
    */

    protected function sendCustomerPaymentStatusNotification(
        Order $order,
        string $oldStatus,
        string $newStatus
    ): void {

        $customer =
            $order->user;

        if (!$customer) {
            return;
        }

        try {

            $customer->notify(
                new OrderStatusUpdatedNotification(
                    order: $order,
                    oldPaymentStatus: $oldStatus
                )
            );

        } catch (\Throwable $e) {

            Log::error(
                'Customer payment notification failed.',
                [
                    'order_id' =>
                        $order->id,

                    'user_id' =>
                        $order->user_id,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | COMBINED ORDER UPDATE NOTIFICATION
    |--------------------------------------------------------------------------
    */

    protected function sendCombinedOrderUpdateNotification(
        Order $order,
        ?string $oldStatus,
        ?string $oldPaymentStatus,
        ?string $oldTrackingNumber,
        ?string $oldAdminNote
    ): void {

        $customer =
            $order->user;

        if (!$customer) {
            return;
        }

        $statusChanged =
            $oldStatus !==
            $order->status;

        $paymentChanged =
            $oldPaymentStatus !==
            $order->payment_status;

        $trackingChanged =
            $oldTrackingNumber !==
            $order->tracking_number;

        $adminNoteChanged =
            $oldAdminNote !==
            $order->admin_note;

        if (
            !$statusChanged &&
            !$paymentChanged &&
            !$trackingChanged &&
            !$adminNoteChanged
        ) {
            return;
        }

        try {

            if (
                $statusChanged &&
                $order->status === 'cancelled'
            ) {

                $customer->notify(
                    new OrderCancelledNotification(
                        $order
                    )
                );

                return;
            }

            if (
                $statusChanged &&
                $order->status === 'delivered'
            ) {

                $customer->notify(
                    new OrderDeliveredNotification(
                        $order
                    )
                );

                return;
            }

            $customer->notify(
                new OrderStatusUpdatedNotification(

                    order: $order,

                    oldStatus:
                        $statusChanged
                            ? $oldStatus
                            : null,

                    oldPaymentStatus:
                        $paymentChanged
                            ? $oldPaymentStatus
                            : null,

                    oldTrackingNumber:
                        $trackingChanged
                            ? $oldTrackingNumber
                            : null,

                    oldAdminNote:
                        $adminNoteChanged
                            ? $oldAdminNote
                            : null
                )
            );

        } catch (\Throwable $e) {

            Log::error(
                'Combined order notification failed.',
                [
                    'order_id' =>
                        $order->id,

                    'user_id' =>
                        $order->user_id,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN + SELLER CANCELLATION NOTIFICATION
    |--------------------------------------------------------------------------
    */

    protected function notifyAdminsAndSellersOrderCancelled(
        Order $order
    ): void {

        $order->loadMissing([
            'user',
            'items.product',
            'items.productVariant',
        ]);

        /*
        |--------------------------------------------------------------------------
        | ADMINS
        |--------------------------------------------------------------------------
        */

        try {

            $admins =
                User::role('admin')->get();

            foreach ($admins as $admin) {

                try {

                    $admin->notify(
                        new OrderCancelledNotification(
                            $order
                        )
                    );

                } catch (\Throwable $e) {

                    Log::error(
                        'Admin cancellation notification failed.',
                        [
                            'order_id' =>
                                $order->id,

                            'admin_id' =>
                                $admin->id,

                            'error' =>
                                $e->getMessage(),
                        ]
                    );
                }
            }

        } catch (\Throwable $e) {

            Log::error(
                'Failed to retrieve admins for cancellation.',
                [
                    'order_id' =>
                        $order->id,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SELLERS
        |--------------------------------------------------------------------------
        */

        $vendorIds =
            $order->items
                ->map(
                    fn ($item) =>
                        $item->vendor_id
                )
                ->filter()
                ->unique()
                ->values();

        if ($vendorIds->isEmpty()) {
            return;
        }

        try {

            $sellers =
                User::role('seller')
                    ->whereHas(
                        'vendor',
                        function ($query) use (
                            $vendorIds
                        ) {

                            $query->whereIn(
                                'id',
                                $vendorIds
                            );
                        }
                    )
                    ->get();

            foreach ($sellers as $seller) {

                try {

                    $seller->notify(
                        new OrderCancelledNotification(
                            $order
                        )
                    );

                } catch (\Throwable $e) {

                    Log::error(
                        'Seller cancellation notification failed.',
                        [
                            'order_id' =>
                                $order->id,

                            'seller_id' =>
                                $seller->id,

                            'error' =>
                                $e->getMessage(),
                        ]
                    );
                }
            }

        } catch (\Throwable $e) {

            Log::error(
                'Failed to retrieve sellers for cancellation.',
                [
                    'order_id' =>
                        $order->id,

                    'vendor_ids' =>
                        $vendorIds->toArray(),

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER CANCEL ORDER
    |--------------------------------------------------------------------------
    */

    public function cancelOrder(
        Order $order
    ): Order {

        if (
            strtolower(
                (string) $order->status
            ) !== 'pending'
        ) {

            throw new \RuntimeException(
                'Only pending orders can be cancelled.'
            );
        }

        $oldStatus =
            strtolower(
                (string) $order->status
            );

        $order = DB::transaction(
            function () use ($order) {

                $order->load([
                    'items.product',
                    'items.productVariant',
                ]);

                $this->restoreOrderStock(
                    $order
                );

                $this->removePendingVendorEarnings(
                    $order
                );

                $order->update([
                    'status' => 'cancelled',
                ]);

                return $this->loadOrder(
                    $order
                );
            }
        );

        $this->sendCustomerOrderStatusNotification(
            $order,
            $oldStatus,
            'cancelled'
        );

        $this->notifyAdminsAndSellersOrderCancelled(
            $order
        );

        return $order;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE TRACKING NUMBER
    |--------------------------------------------------------------------------
    */

    public function updateTrackingNumber(
        Order $order,
        ?string $trackingNumber
    ): Order {

        $oldTrackingNumber =
            $order->tracking_number;

        $trackingNumber =
            $trackingNumber !== null
                ? trim($trackingNumber)
                : null;

        if (
            $oldTrackingNumber ===
            $trackingNumber
        ) {

            return $this->loadOrder(
                $order
            );
        }

        $order->update([
            'tracking_number' =>
                $trackingNumber,
        ]);

        $order =
            $this->loadOrder(
                $order
            );

        $this->sendCustomerOrderFieldNotification(
            $order,
            oldTrackingNumber:
                $oldTrackingNumber
        );

        return $order;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ADMIN NOTE
    |--------------------------------------------------------------------------
    */

    public function updateAdminNote(
        Order $order,
        ?string $adminNote
    ): Order {

        $oldAdminNote =
            $order->admin_note;

        $adminNote =
            $adminNote !== null
                ? trim($adminNote)
                : null;

        if (
            $oldAdminNote ===
            $adminNote
        ) {

            return $this->loadOrder(
                $order
            );
        }

        $order->update([
            'admin_note' =>
                $adminNote,
        ]);

        $order =
            $this->loadOrder(
                $order
            );

        $this->sendCustomerOrderFieldNotification(
            $order,
            oldAdminNote:
                $oldAdminNote
        );

        return $order;
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER FIELD NOTIFICATION
    |--------------------------------------------------------------------------
    */

    protected function sendCustomerOrderFieldNotification(
        Order $order,
        ?string $oldTrackingNumber = null,
        ?string $oldAdminNote = null
    ): void {

        if (!$order->user) {
            return;
        }

        $trackingChanged =
            $oldTrackingNumber !==
            $order->tracking_number;

        $adminNoteChanged =
            $oldAdminNote !==
            $order->admin_note;

        if (
            !$trackingChanged &&
            !$adminNoteChanged
        ) {
            return;
        }

        try {

            $order->user->notify(
                new OrderStatusUpdatedNotification(

                    order: $order,

                    oldTrackingNumber:
                        $trackingChanged
                            ? $oldTrackingNumber
                            : null,

                    oldAdminNote:
                        $adminNoteChanged
                            ? $oldAdminNote
                            : null
                )
            );

        } catch (\Throwable $e) {

            Log::error(
                'Customer field notification failed.',
                [
                    'order_id' =>
                        $order->id,

                    'user_id' =>
                        $order->user_id,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RESTORE ORDER STOCK
    |--------------------------------------------------------------------------
    */

    protected function restoreOrderStock(
        Order $order
    ): void {

        $order->loadMissing([
            'items.product',
            'items.productVariant',
        ]);

        foreach ($order->items as $item) {

            if ($item->product) {

                $item->product->increment(
                    'stock',
                    (int) $item->quantity
                );
            }

            if ($item->productVariant) {

                $item->productVariant->increment(
                    'stock',
                    (int) $item->quantity
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE ORDER
    |--------------------------------------------------------------------------
    */

    public function deleteOrder(
        Order $order
    ): bool {

        return DB::transaction(
            function () use ($order) {

                $order->load([
                    'items.product',
                    'items.productVariant',
                ]);

                if (
                    !in_array(
                        strtolower(
                            (string) $order->status
                        ),
                        [
                            'delivered',
                            'cancelled',
                        ],
                        true
                    )
                ) {

                    $this->restoreOrderStock(
                        $order
                    );
                }

                return (bool)
                    $order->delete();
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SELLER ORDERS
    |--------------------------------------------------------------------------
    */

    public function sellerOrders(
        int $vendorId
    ) {

        return Order::with([
            'user',
            'coupon',
            'items.vendor',
            'items.product',
            'items.productVariant',
            'items.vendorEarning',
            'payment',
        ])
            ->whereHas(
                'items',
                function ($query) use (
                    $vendorId
                ) {

                    $query->where(
                        'vendor_id',
                        $vendorId
                    );
                }
            )
            ->latest()
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER ORDERS
    |--------------------------------------------------------------------------
    */

    public function customerOrders()
    {
        return Order::with([
            'coupon',
            'items.vendor',
            'items.product',
            'items.productVariant',
            'items.vendorEarning',
            'payment',
        ])
            ->where(
                'user_id',
                Auth::id()
            )
            ->latest()
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN ORDERS
    |--------------------------------------------------------------------------
    */

    public function adminOrders()
    {
        return Order::with([
            'user',
            'coupon',
            'items.vendor',
            'items.product',
            'items.productVariant',
            'items.vendorEarning',
            'payment',
        ])
            ->latest()
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | FIND ORDER
    |--------------------------------------------------------------------------
    */

    public function findOrder(
        int $id
    ): ?Order {

        return Order::with([
            'user',
            'coupon',
            'items.vendor',
            'items.product',
            'items.productVariant',
            'items.vendorEarning',
            'payment',
        ])
            ->find($id);
    }

    /*
    |--------------------------------------------------------------------------
    | FIND SELLER ORDER
    |--------------------------------------------------------------------------
    */

    public function findSellerOrder(
        int $vendorId,
        int $orderId
    ): ?Order {

        return Order::with([
            'user',
            'coupon',
            'items.vendor',
            'items.product',
            'items.productVariant',
            'items.vendorEarning',
            'payment',
        ])
            ->whereHas(
                'items',
                function ($query) use (
                    $vendorId
                ) {

                    $query->where(
                        'vendor_id',
                        $vendorId
                    );
                }
            )
            ->find($orderId);
    }

    /*
    |--------------------------------------------------------------------------
    | FIND CUSTOMER ORDER
    |--------------------------------------------------------------------------
    */

    public function findCustomerOrder(
        int $orderId
    ): ?Order {

        return Order::with([
            'user',
            'coupon',
            'items.vendor',
            'items.product',
            'items.productVariant',
            'items.vendorEarning',
            'payment',
        ])
            ->where(
                'user_id',
                Auth::id()
            )
            ->find($orderId);
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD ORDER
    |--------------------------------------------------------------------------
    */

    protected function loadOrder(
        Order $order
    ): Order {

        return $order
            ->fresh()
            ->load([
                'user',
                'coupon',
                'items.vendor',
                'items.product',
                'items.productVariant',
                'items.vendorEarning',
                'payment',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE ORDER STATUS
    |--------------------------------------------------------------------------
    */

    protected function validateOrderStatus(
        string $status
    ): void {

        if (
            !in_array(
                $status,
                $this->allowedStatuses,
                true
            )
        ) {

            throw new \InvalidArgumentException(
                'Invalid order status. Allowed statuses: ' .
                implode(
                    ', ',
                    $this->allowedStatuses
                )
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE PAYMENT STATUS
    |--------------------------------------------------------------------------
    */

    protected function validatePaymentStatus(
        string $status
    ): void {

        if (
            !in_array(
                $status,
                $this->allowedPaymentStatuses,
                true
            )
        ) {

            throw new \InvalidArgumentException(
                'Invalid payment status. Allowed statuses: ' .
                implode(
                    ', ',
                    $this->allowedPaymentStatuses
                )
            );
        }
    }
}