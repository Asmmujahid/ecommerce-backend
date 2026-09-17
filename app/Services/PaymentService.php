<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\VendorEarning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /*
    |--------------------------------------------------------------------------
    | COMMISSION SETTINGS
    |--------------------------------------------------------------------------
    */

    protected function marketplaceCommissionRate(): float
    {
        return max(
            0,
            min(
                100,
                (float) config(
                    'marketplace.commission_rate',
                    10
                )
            )
        );
    }

    protected function vendorSellingFeeRate(): float
    {
        return max(
            0,
            min(
                100,
                (float) config(
                    'marketplace.vendor_selling_fee_rate',
                    10
                )
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE PAYMENT
    |--------------------------------------------------------------------------
    */

    public function createPayment(
        Order $order,
        array $data = []
    ): Payment {
        return DB::transaction(function () use ($order, $data) {

            $transactionId =
                isset($data['transaction_id'])
                    ? trim(
                        (string) $data['transaction_id']
                    )
                    : null;

            if ($transactionId === '') {
                $transactionId = null;
            }

            return Payment::create([
                'order_id' => $order->id,

                'user_id' => $order->user_id,

                'amount' =>
                    $order->total_amount
                    ?? $order->total
                    ?? 0,

                'payment_method' =>
                    $data['payment_method'] ?? null,

                'transaction_id' =>
                    $transactionId,

                'status' => 'pending',

                'paid_at' => null,
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | FIND PAYMENT
    |--------------------------------------------------------------------------
    */

    public function findPayment(int $id): ?Payment
    {
        return Payment::with([
            'user',
            'order',
            'order.coupon',
            'order.items.vendor',
            'order.items.product',
            'order.items.productVariant',
            'order.items.vendorEarning',
        ])->find($id);
    }

    /*
    |--------------------------------------------------------------------------
    | ALL PAYMENTS
    |--------------------------------------------------------------------------
    */

    public function allPayments()
    {
        return Payment::with([
            'user',
            'order',
            'order.items.vendor',
            'order.items.product',
            'order.items.productVariant',
            'order.items.vendorEarning',
        ])
            ->latest()
            ->paginate(15);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PAYMENT
    |--------------------------------------------------------------------------
    |
    | CENTRAL PAYMENT UPDATE METHOD.
    |
    | Customer payment and vendor settlement are different concepts.
    |
    | payments.paid_at
    |     = when customer payment was received.
    |
    | vendor_earnings.paid_at
    |     = when vendor actually received/was settled.
    |
    |--------------------------------------------------------------------------
    */

    public function updatePayment(
        Payment $payment,
        string $status,
        ?string $transactionId = null,
        ?string $paymentMethod = null
    ): Payment {
        $status = strtolower(trim($status));

        return DB::transaction(function () use (
            $payment,
            $status,
            $transactionId,
            $paymentMethod
        ) {

            $payment->load([
                'order.items.product',
                'order.items.vendor',
                'order.items.productVariant',
            ]);

            /*
            |--------------------------------------------------------------------------
            | TRANSACTION ID
            |--------------------------------------------------------------------------
            */

            $payment->transaction_id = $transactionId;

            /*
            |--------------------------------------------------------------------------
            | PAYMENT METHOD
            |--------------------------------------------------------------------------
            */

            if ($paymentMethod !== null) {
                $payment->payment_method = $paymentMethod;
            }

            /*
            |--------------------------------------------------------------------------
            | PAID
            |--------------------------------------------------------------------------
            */

            if ($status === 'paid') {

                $wasAlreadyPaid =
                    strtolower(
                        trim(
                            (string) $payment->status
                        )
                    ) === 'paid';

                $payment->status = 'paid';

                /*
                |--------------------------------------------------------------------------
                | CUSTOMER PAYMENT DATE
                |--------------------------------------------------------------------------
                */

                if (!$payment->paid_at) {
                    $payment->paid_at = now();
                }

                $payment->save();

                /*
                |--------------------------------------------------------------------------
                | UPDATE ORDER
                |--------------------------------------------------------------------------
                */

                if ($payment->order) {

                    $payment->order->update([
                        'payment_status' => 'paid',
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | CREATE / UPDATE VENDOR EARNINGS
                    |--------------------------------------------------------------------------
                    |
                    | IMPORTANT:
                    |
                    | Vendor-owned product:
                    |     Customer pays Vendor.
                    |     Vendor's 90% is already received.
                    |     Vendor earning = PAID.
                    |
                    | Admin-owned product:
                    |     Customer pays Admin.
                    |     Vendor receives 10% later.
                    |     Vendor earning = PENDING.
                    |
                    |--------------------------------------------------------------------------
                    */

                    $this->syncVendorEarnings(
                        $payment->order,
                        $wasAlreadyPaid,
                        $payment->paid_at
                    );
                }

                return $this->findPayment(
                    $payment->id
                );
            }

            /*
            |--------------------------------------------------------------------------
            | FAILED
            |--------------------------------------------------------------------------
            */

            if ($status === 'failed') {

                $payment->status = 'failed';

                /*
                |--------------------------------------------------------------------------
                | Failed customer payment has no paid date.
                |--------------------------------------------------------------------------
                */

                $payment->paid_at = null;

                $payment->save();

                if ($payment->order) {

                    $payment->order->update([
                        'payment_status' => 'failed',
                    ]);

                    $this->reverseEarnings(
                        $payment->order
                    );
                }

                return $this->findPayment(
                    $payment->id
                );
            }

            /*
            |--------------------------------------------------------------------------
            | REFUNDED
            |--------------------------------------------------------------------------
            */

            if ($status === 'refunded') {

                $payment->status = 'refunded';

                /*
                |--------------------------------------------------------------------------
                | KEEP CUSTOMER PAYMENT DATE
                |--------------------------------------------------------------------------
                |
                | paid_at means:
                | "When did the customer payment happen?"
                |
                | Therefore it should remain after refund.
                |--------------------------------------------------------------------------
                */

                $payment->save();

                if ($payment->order) {

                    $payment->order->update([
                        'payment_status' => 'refunded',
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Refund invalidates vendor earnings.
                    |--------------------------------------------------------------------------
                    */

                    $this->reverseEarnings(
                        $payment->order
                    );
                }

                return $this->findPayment(
                    $payment->id
                );
            }

            /*
            |--------------------------------------------------------------------------
            | PENDING
            |--------------------------------------------------------------------------
            */

            if ($status === 'pending') {

                $payment->status = 'pending';

                $payment->paid_at = null;

                $payment->save();

                if ($payment->order) {

                    $payment->order->update([
                        'payment_status' => 'pending',
                    ]);

                    $this->reverseEarnings(
                        $payment->order
                    );
                }

                return $this->findPayment(
                    $payment->id
                );
            }

            /*
            |--------------------------------------------------------------------------
            | INVALID STATUS
            |--------------------------------------------------------------------------
            */

            throw new \InvalidArgumentException(
                'Invalid payment status.'
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | MARK PAYMENT AS PAID
    |--------------------------------------------------------------------------
    */

    public function markAsPaid(
        Payment $payment,
        ?string $transactionId = null,
        ?string $paymentMethod = null
    ): Payment {
        return $this->updatePayment(
            $payment,
            'paid',
            $transactionId,
            $paymentMethod
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SYNC VENDOR EARNINGS
    |--------------------------------------------------------------------------
    |
    | BUSINESS RULE:
    |
    | ------------------------------------------------------------------------
    | VENDOR-OWNED PRODUCT
    | ------------------------------------------------------------------------
    |
    | Customer pays Vendor.
    |
    | Customer payment = 100%
    | Admin commission = 10%
    | Vendor receives = 90%
    |
    | Therefore VendorEarning:
    |
    | status  = paid
    | paid_at = customer payment paid_at
    |
    | ------------------------------------------------------------------------
    | ADMIN-OWNED PRODUCT
    | ------------------------------------------------------------------------
    |
    | Customer pays Admin.
    |
    | Customer payment = 100%
    | Vendor selling fee = 10%
    | Admin keeps = 90%
    |
    | Therefore VendorEarning:
    |
    | status  = pending
    | paid_at = null
    |
    | It becomes paid only when Admin actually settles the vendor fee.
    |
    |--------------------------------------------------------------------------
    */

    public function syncVendorEarnings(
        Order $order,
        bool $reopenReversed = false,
        $customerPaidAt = null
    ): void {

        $order->load([
            'items.product',
            'items.vendor',
            'items.productVariant',
        ]);

        $marketplaceRate =
            $this->marketplaceCommissionRate();

        $sellingFeeRate =
            $this->vendorSellingFeeRate();

        /*
        |--------------------------------------------------------------------------
        | CUSTOMER PAYMENT DATE
        |--------------------------------------------------------------------------
        */

        if (!$customerPaidAt) {
            $customerPaidAt = now();
        }

        /*
        |--------------------------------------------------------------------------
        | SUBTOTAL
        |--------------------------------------------------------------------------
        */

        $itemsSubtotal = round(
            (float) $order->items->sum(
                fn ($item) => (float) $item->total
            ),
            2
        );

        /*
        |--------------------------------------------------------------------------
        | ORDER DISCOUNT
        |--------------------------------------------------------------------------
        */

        $orderDiscount = (float) (
            $order->discount_amount
            ?? $order->discount
            ?? 0
        );

        $orderDiscount = max(
            0,
            round(
                $orderDiscount,
                2
            )
        );

        /*
        |--------------------------------------------------------------------------
        | PROCESS ORDER ITEMS
        |--------------------------------------------------------------------------
        */

        foreach ($order->items as $item) {

            $product = $item->product;

            if (!$product) {

                Log::warning(
                    'Vendor earning skipped: product not found.',
                    [
                        'order_id' =>
                            $order->id,

                        'order_item_id' =>
                            $item->id,

                        'product_id' =>
                            $item->product_id,
                    ]
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | SELLING VENDOR
            |--------------------------------------------------------------------------
            */

            $vendorId =
                $item->vendor_id
                ?? $product->vendor_id;

            if (!$vendorId) {

                Log::warning(
                    'Vendor earning skipped: vendor not found.',
                    [
                        'order_id' =>
                            $order->id,

                        'order_item_id' =>
                            $item->id,

                        'product_id' =>
                            $product->id,
                    ]
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | REPAIR VENDOR SNAPSHOT
            |--------------------------------------------------------------------------
            */

            if (!$item->vendor_id) {
                $item->vendor_id = $vendorId;
            }

            /*
            |--------------------------------------------------------------------------
            | PRODUCT OWNER TYPE
            |--------------------------------------------------------------------------
            */

            $productOwnerType =
                strtolower(
                    trim(
                        (string) (
                            $product->owner_type
                            ?? ''
                        )
                    )
                );

            if (
                !in_array(
                    $productOwnerType,
                    ['admin', 'vendor'],
                    true
                )
            ) {

                Log::warning(
                    'Vendor earning skipped: invalid product owner type.',
                    [
                        'order_id' =>
                            $order->id,

                        'order_item_id' =>
                            $item->id,

                        'product_id' =>
                            $product->id,

                        'owner_type' =>
                            $product->owner_type,
                    ]
                );

                continue;
            }

            $ownerType =
                $productOwnerType;

            /*
            |--------------------------------------------------------------------------
            | REPAIR OWNER SNAPSHOT
            |--------------------------------------------------------------------------
            */

            if (
                $item->product_owner_type !==
                $ownerType
            ) {

                $item->product_owner_type =
                    $ownerType;
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE SNAPSHOT
            |--------------------------------------------------------------------------
            */

            if ($item->isDirty()) {
                $item->save();
            }

            /*
            |--------------------------------------------------------------------------
            | ITEM TOTAL
            |--------------------------------------------------------------------------
            */

            $itemTotal = max(
                0,
                round(
                    (float) $item->total,
                    2
                )
            );

            /*
            |--------------------------------------------------------------------------
            | DISCOUNT ALLOCATION
            |--------------------------------------------------------------------------
            */

            $discountShare = 0;

            if (
                $orderDiscount > 0 &&
                $itemsSubtotal > 0
            ) {

                $discountShare =
                    round(
                        (
                            $itemTotal /
                            $itemsSubtotal
                        ) *
                        $orderDiscount,
                        2
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | GROSS AMOUNT
            |--------------------------------------------------------------------------
            */

            $grossAmount =
                round(
                    max(
                        0,
                        $itemTotal -
                        $discountShare
                    ),
                    2
                );

            /*
            |--------------------------------------------------------------------------
            | ACCOUNTING
            |--------------------------------------------------------------------------
            */

            if ($ownerType === 'vendor') {

                /*
                |--------------------------------------------------------------------------
                | VENDOR-OWNED PRODUCT
                |--------------------------------------------------------------------------
                |
                | Customer pays Vendor.
                |
                | Customer = 100%
                | Admin commission = 10%
                | Vendor = 90%
                |
                |--------------------------------------------------------------------------
                */

                $rate =
                    $marketplaceRate;

                $commissionAmount =
                    round(
                        $grossAmount *
                        $marketplaceRate /
                        100,
                        2
                    );

                $netAmount =
                    round(
                        max(
                            0,
                            $grossAmount -
                            $commissionAmount
                        ),
                        2
                    );

                $calculationType =
                    'marketplace_commission';

            } else {

                /*
                |--------------------------------------------------------------------------
                | ADMIN-OWNED PRODUCT
                |--------------------------------------------------------------------------
                |
                | Customer pays Admin.
                |
                | Customer = 100%
                | Vendor fee = 10%
                | Admin = 90%
                |
                |--------------------------------------------------------------------------
                */

                $rate =
                    $sellingFeeRate;

                $commissionAmount =
                    round(
                        $grossAmount *
                        $sellingFeeRate /
                        100,
                        2
                    );

                /*
                |--------------------------------------------------------------------------
                | Vendor's earning is the selling/service fee.
                |--------------------------------------------------------------------------
                */

                $netAmount =
                    $commissionAmount;

                $calculationType =
                    'vendor_selling_fee';
            }

            /*
            |--------------------------------------------------------------------------
            | FIND EXISTING EARNING
            |--------------------------------------------------------------------------
            */

            $earning =
                VendorEarning::where(
                    'order_item_id',
                    $item->id
                )->first();

            /*
            |--------------------------------------------------------------------------
            | DETERMINE VENDOR EARNING STATUS
            |--------------------------------------------------------------------------
            |
            | THIS IS THE IMPORTANT FIX.
            |
            | Vendor-owned:
            |     Customer paid Vendor.
            |     Therefore vendor earning is already PAID.
            |
            | Admin-owned:
            |     Customer paid Admin.
            |     Vendor fee has not yet been paid.
            |     Therefore vendor earning is PENDING.
            |
            |--------------------------------------------------------------------------
            */

            if ($ownerType === 'vendor') {

                /*
                |--------------------------------------------------------------------------
                | VENDOR-OWNED = ALREADY PAID
                |--------------------------------------------------------------------------
                */

                $status = 'paid';

                /*
                |--------------------------------------------------------------------------
                | If it was reversed because of refund/failure and payment
                | becomes paid again, reopen it as paid.
                |--------------------------------------------------------------------------
                */

                $vendorPaidAt =
                    $earning?->paid_at
                    ?? $customerPaidAt;

            } else {

                /*
                |--------------------------------------------------------------------------
                | ADMIN-OWNED = WAITING FOR ADMIN PAYOUT
                |--------------------------------------------------------------------------
                */

                $status =
                    $earning?->status
                    ?? 'pending';

                /*
                |--------------------------------------------------------------------------
                | Reopen only when explicitly requested.
                |--------------------------------------------------------------------------
                */

                if (
                    $status === 'reversed' &&
                    $reopenReversed
                ) {
                    $status = 'pending';
                }

                /*
                |--------------------------------------------------------------------------
                | Admin-owned vendor earning is not paid yet.
                |--------------------------------------------------------------------------
                */

                $vendorPaidAt = null;

                /*
                |--------------------------------------------------------------------------
                | Preserve actual payout date if it was already paid.
                |--------------------------------------------------------------------------
                */

                if (
                    $earning &&
                    $earning->status === 'paid'
                ) {

                    $status = 'paid';

                    $vendorPaidAt =
                        $earning->paid_at;
                }

                /*
                |--------------------------------------------------------------------------
                | If earning was reversed and reopened, clear payout date.
                |--------------------------------------------------------------------------
                */

                if (
                    $earning &&
                    $earning->status === 'reversed' &&
                    $status === 'pending'
                ) {

                    $vendorPaidAt = null;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | EARNING DATA
            |--------------------------------------------------------------------------
            */

            $earningData = [

                'vendor_id' =>
                    $vendorId,

                'order_id' =>
                    $order->id,

                'order_item_id' =>
                    $item->id,

                'gross_amount' =>
                    $grossAmount,

                'commission_rate' =>
                    round(
                        $rate,
                        2
                    ),

                'commission_amount' =>
                    $commissionAmount,

                'net_amount' =>
                    $netAmount,

                'status' =>
                    $status,

                'paid_at' =>
                    $vendorPaidAt,
            ];

            /*
            |--------------------------------------------------------------------------
            | CREATE / UPDATE
            |--------------------------------------------------------------------------
            */

            VendorEarning::updateOrCreate(
                [
                    'order_item_id' =>
                        $item->id,
                ],
                $earningData
            );

            /*
            |--------------------------------------------------------------------------
            | LOG
            |--------------------------------------------------------------------------
            */

            Log::info(
                'Vendor earning synchronized.',
                [
                    'order_id' =>
                        $order->id,

                    'order_item_id' =>
                        $item->id,

                    'vendor_id' =>
                        $vendorId,

                    'owner_type' =>
                        $ownerType,

                    'calculation_type' =>
                        $calculationType,

                    'gross_amount' =>
                        $grossAmount,

                    'rate' =>
                        $rate,

                    'commission_amount' =>
                        $commissionAmount,

                    'net_amount' =>
                        $netAmount,

                    'status' =>
                        $status,

                    'vendor_paid_at' =>
                        $vendorPaidAt,

                    'customer_paid_at' =>
                        $customerPaidAt,

                    'reopen_reversed' =>
                        $reopenReversed,
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REPAIR OLD PAID ORDERS
    |--------------------------------------------------------------------------
    */

    public function repairPaidOrderEarnings(
        ?int $vendorId = null
    ): int {

        $payments =
            Payment::with([
                'order.items.product',
                'order.items.vendor',
                'order.items.productVariant',
            ])
                ->whereRaw(
                    'LOWER(status) = ?',
                    ['paid']
                )
                ->whereNotNull('order_id')
                ->get();

        $processed = 0;

        foreach ($payments as $payment) {

            $order =
                $payment->order;

            if (!$order) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | DO NOT REPAIR CANCELLED ORDERS
            |--------------------------------------------------------------------------
            */

            if (
                isset($order->status) &&
                strtolower(
                    trim(
                        (string) $order->status
                    )
                ) === 'cancelled'
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | SELLER-SPECIFIC REPAIR
            |--------------------------------------------------------------------------
            */

            if ($vendorId !== null) {

                $hasVendorItems =
                    $order->items->contains(
                        function ($item) use ($vendorId) {

                            $itemVendorId =
                                $item->vendor_id
                                ?? $item->product?->vendor_id;

                            return (int) $itemVendorId ===
                                (int) $vendorId;
                        }
                    );

                if (!$hasVendorItems) {
                    continue;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | REPAIR
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | Pass payment.paid_at so vendor-owned earnings get the correct
            | vendor paid date.
            |--------------------------------------------------------------------------
            */

            $this->syncVendorEarnings(
                $order,
                false,
                $payment->paid_at
            );

            $processed++;
        }

        return $processed;
    }

    /*
    |--------------------------------------------------------------------------
    | MARK PAYMENT AS FAILED
    |--------------------------------------------------------------------------
    */

    public function markAsFailed(
        Payment $payment,
        ?string $transactionId = null,
        ?string $paymentMethod = null
    ): Payment {

        return $this->updatePayment(
            $payment,
            'failed',
            $transactionId,
            $paymentMethod
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REFUND PAYMENT
    |--------------------------------------------------------------------------
    */

    public function refundPayment(
        Payment $payment,
        ?string $transactionId = null,
        ?string $paymentMethod = null
    ): Payment {

        return $this->updatePayment(
            $payment,
            'refunded',
            $transactionId,
            $paymentMethod
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MARK PAYMENT AS PENDING
    |--------------------------------------------------------------------------
    */

    public function markAsPending(
        Payment $payment,
        ?string $transactionId = null,
        ?string $paymentMethod = null
    ): Payment {

        return $this->updatePayment(
            $payment,
            'pending',
            $transactionId,
            $paymentMethod
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REVERSE EARNINGS
    |--------------------------------------------------------------------------
    |
    | When customer payment fails/refunds/pending, the vendor earning
    | should no longer be considered active.
    |
    | We reverse BOTH pending and paid earnings because a refund means
    | the original earning is no longer valid.
    |
    |--------------------------------------------------------------------------
    */

    protected function reverseEarnings(
        Order $order
    ): void {

        VendorEarning::where(
            'order_id',
            $order->id
        )
            ->whereIn(
                'status',
                [
                    'pending',
                    'paid',
                ]
            )
            ->update([
                'status' => 'reversed',
                'paid_at' => null,
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SELLER PAYMENTS
    |--------------------------------------------------------------------------
    */

    public function sellerPayments(
        int $vendorId
    ) {

        return Payment::with([
            'user',
            'order',
            'order.coupon',

            'order.items' => function ($query) use ($vendorId) {
                $query->where(
                    'vendor_id',
                    $vendorId
                );
            },

            'order.items.vendor',
            'order.items.product',
            'order.items.productVariant',
            'order.items.vendorEarning',
        ])
            ->whereHas(
                'order.items',
                function ($query) use ($vendorId) {
                    $query->where(
                        'vendor_id',
                        $vendorId
                    );
                }
            )
            ->latest()
            ->paginate(15);
    }

    /*
    |--------------------------------------------------------------------------
    | SELLER EARNINGS
    |--------------------------------------------------------------------------
    */

    public function sellerEarnings(
        int $vendorId
    ): array {

        $this->repairPaidOrderEarnings(
            $vendorId
        );

        $baseQuery =
            VendorEarning::where(
                'vendor_id',
                $vendorId
            );

        $activeQuery =
            (clone $baseQuery)
                ->where(
                    'status',
                    '!=',
                    'reversed'
                );

        $grossSales =
            (float) (clone $activeQuery)
                ->sum('gross_amount');

        $marketplaceCommission =
            (float) (clone $activeQuery)
                ->whereHas(
                    'orderItem',
                    function ($query) {
                        $query->where(
                            'product_owner_type',
                            'vendor'
                        );
                    }
                )
                ->sum('commission_amount');

        $sellingServiceFees =
            (float) (clone $activeQuery)
                ->whereHas(
                    'orderItem',
                    function ($query) {
                        $query->where(
                            'product_owner_type',
                            'admin'
                        );
                    }
                )
                ->sum('commission_amount');

        $totalEarnings =
            (float) (clone $activeQuery)
                ->sum('net_amount');

        $paidEarnings =
            (float) (clone $baseQuery)
                ->where(
                    'status',
                    'paid'
                )
                ->sum('net_amount');

        $pendingEarnings =
            (float) (clone $baseQuery)
                ->where(
                    'status',
                    'pending'
                )
                ->sum('net_amount');

        $reversedEarnings =
            (float) (clone $baseQuery)
                ->where(
                    'status',
                    'reversed'
                )
                ->sum('net_amount');

        return [

            'gross_sales' =>
                round(
                    $grossSales,
                    2
                ),

            'gross_earnings' =>
                round(
                    $grossSales,
                    2
                ),

            /*
            |--------------------------------------------------------------------------
            | Admin commission from vendor-owned products.
            |--------------------------------------------------------------------------
            */

            'marketplace_commission' =>
                round(
                    $marketplaceCommission,
                    2
                ),

            'admin_commission' =>
                round(
                    $marketplaceCommission,
                    2
                ),

            /*
            |--------------------------------------------------------------------------
            | Vendor selling fees from admin-owned products.
            |--------------------------------------------------------------------------
            */

            'vendor_selling_fees' =>
                round(
                    $sellingServiceFees,
                    2
                ),

            'selling_service_fees' =>
                round(
                    $sellingServiceFees,
                    2
                ),

            /*
            |--------------------------------------------------------------------------
            | Total money belonging to vendor.
            |--------------------------------------------------------------------------
            */

            'vendor_earnings' =>
                round(
                    $totalEarnings,
                    2
                ),

            'total_vendor_earnings' =>
                round(
                    $totalEarnings,
                    2
                ),

            'total_seller_earnings' =>
                round(
                    $totalEarnings,
                    2
                ),

            'total_earnings' =>
                round(
                    $totalEarnings,
                    2
                ),

            /*
            |--------------------------------------------------------------------------
            | Already paid to vendor.
            |--------------------------------------------------------------------------
            */

            'paid_earnings' =>
                round(
                    $paidEarnings,
                    2
                ),

            'paid_vendor_earnings' =>
                round(
                    $paidEarnings,
                    2
                ),

            /*
            |--------------------------------------------------------------------------
            | Still owed to vendor.
            |--------------------------------------------------------------------------
            */

            'pending_earnings' =>
                round(
                    $pendingEarnings,
                    2
                ),

            'pending_vendor_earnings' =>
                round(
                    $pendingEarnings,
                    2
                ),

            /*
            |--------------------------------------------------------------------------
            | Reversed.
            |--------------------------------------------------------------------------
            */

            'reversed_earnings' =>
                round(
                    $reversedEarnings,
                    2
                ),

            'reversed_vendor_earnings' =>
                round(
                    $reversedEarnings,
                    2
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN EARNINGS
    |--------------------------------------------------------------------------
    */

    public function adminEarnings(): array
    {
        $this->repairPaidOrderEarnings();

        $baseQuery =
            VendorEarning::query();

        $activeQuery =
            (clone $baseQuery)
                ->where(
                    'status',
                    '!=',
                    'reversed'
                );

        $grossSales =
            (float) (clone $activeQuery)
                ->sum('gross_amount');

        /*
        |--------------------------------------------------------------------------
        | Admin commission from vendor-owned products.
        |--------------------------------------------------------------------------
        */

        $marketplaceCommission =
            (float) (clone $activeQuery)
                ->whereHas(
                    'orderItem',
                    function ($query) {
                        $query->where(
                            'product_owner_type',
                            'vendor'
                        );
                    }
                )
                ->sum('commission_amount');

        /*
        |--------------------------------------------------------------------------
        | Vendor fee from admin-owned products.
        |--------------------------------------------------------------------------
        */

        $vendorSellingFees =
            (float) (clone $activeQuery)
                ->whereHas(
                    'orderItem',
                    function ($query) {
                        $query->where(
                            'product_owner_type',
                            'admin'
                        );
                    }
                )
                ->sum('commission_amount');

        /*
        |--------------------------------------------------------------------------
        | Total vendor money.
        |--------------------------------------------------------------------------
        */

        $vendorEarnings =
            (float) (clone $activeQuery)
                ->sum('net_amount');

        /*
        |--------------------------------------------------------------------------
        | Admin's actual net.
        |--------------------------------------------------------------------------
        */

        $adminNet =
            round(
                $grossSales -
                $vendorEarnings,
                2
            );

        /*
        |--------------------------------------------------------------------------
        | Vendor earning statuses.
        |--------------------------------------------------------------------------
        */

        $paidVendorEarnings =
            (float) (clone $baseQuery)
                ->where(
                    'status',
                    'paid'
                )
                ->sum('net_amount');

        $pendingVendorEarnings =
            (float) (clone $baseQuery)
                ->where(
                    'status',
                    'pending'
                )
                ->sum('net_amount');

        $reversedVendorEarnings =
            (float) (clone $baseQuery)
                ->where(
                    'status',
                    'reversed'
                )
                ->sum('net_amount');

        return [

            'total_gross_sales' =>
                round(
                    $grossSales,
                    2
                ),

            'total_admin_commission' =>
                round(
                    $marketplaceCommission,
                    2
                ),

            'total_vendor_selling_fees' =>
                round(
                    $vendorSellingFees,
                    2
                ),

            'total_vendor_earnings' =>
                round(
                    $vendorEarnings,
                    2
                ),

            'total_admin_net' =>
                round(
                    $adminNet,
                    2
                ),

            'paid_vendor_earnings' =>
                round(
                    $paidVendorEarnings,
                    2
                ),

            'pending_vendor_earnings' =>
                round(
                    $pendingVendorEarnings,
                    2
                ),

            'reversed_vendor_earnings' =>
                round(
                    $reversedVendorEarnings,
                    2
                ),

            'gross_sales' =>
                round(
                    $grossSales,
                    2
                ),

            'gross_earnings' =>
                round(
                    $grossSales,
                    2
                ),

            'marketplace_commission' =>
                round(
                    $marketplaceCommission,
                    2
                ),

            'admin_commission' =>
                round(
                    $marketplaceCommission,
                    2
                ),

            'vendor_selling_fees' =>
                round(
                    $vendorSellingFees,
                    2
                ),

            'selling_service_fees' =>
                round(
                    $vendorSellingFees,
                    2
                ),

            'total_earnings' =>
                round(
                    $vendorEarnings,
                    2
                ),

            'paid_earnings' =>
                round(
                    $paidVendorEarnings,
                    2
                ),

            'pending_earnings' =>
                round(
                    $pendingVendorEarnings,
                    2
                ),

            'reversed_earnings' =>
                round(
                    $reversedVendorEarnings,
                    2
                ),
        ];
    }
}