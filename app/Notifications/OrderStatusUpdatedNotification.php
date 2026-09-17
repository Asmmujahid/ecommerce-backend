<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * The order.
     */
    protected Order $order;

    /**
     * Previous order status.
     */
    protected ?string $oldStatus;

    /**
     * Previous payment status.
     */
    protected ?string $oldPaymentStatus;

    /**
     * Previous tracking number.
     */
    protected ?string $oldTrackingNumber;

    /**
     * Previous admin note.
     */
    protected ?string $oldAdminNote;

    /**
     * Create notification.
     */
    public function __construct(
        Order $order,
        ?string $oldStatus = null,
        ?string $oldPaymentStatus = null,
        ?string $oldTrackingNumber = null,
        ?string $oldAdminNote = null
    ) {
        $this->order =
            $order->loadMissing([
                'user',
                'items.product',
                'items.productVariant',
            ]);

        $this->oldStatus =
            $oldStatus;

        $this->oldPaymentStatus =
            $oldPaymentStatus;

        $this->oldTrackingNumber =
            $oldTrackingNumber;

        $this->oldAdminNote =
            $oldAdminNote;
    }

    /**
     * ==========================================================
     * CHANNELS
     * ==========================================================
     */
    public function via(object $notifiable): array
    {
        return [
            'database',
        ];
    }

    /**
     * ==========================================================
     * DATABASE NOTIFICATION
     * ==========================================================
     */
    public function toDatabase(object $notifiable): array
    {
        $changes = [];

        /*
         * ------------------------------------------------------
         * ORDER STATUS
         * ------------------------------------------------------
         */
        if (
            $this->oldStatus !== null &&
            $this->oldStatus !==
                $this->order->status
        ) {
            $changes[] =
                'Order status changed from "' .
                $this->formatStatus(
                    $this->oldStatus
                ) .
                '" to "' .
                $this->formatStatus(
                    $this->order->status
                ) .
                '".';
        }

        /*
         * ------------------------------------------------------
         * PAYMENT STATUS
         * ------------------------------------------------------
         */
        if (
            $this->oldPaymentStatus !== null &&
            $this->oldPaymentStatus !==
                $this->order->payment_status
        ) {
            $changes[] =
                'Payment status changed from "' .
                $this->formatStatus(
                    $this->oldPaymentStatus
                ) .
                '" to "' .
                $this->formatStatus(
                    $this->order->payment_status
                ) .
                '".';
        }

        /*
         * ------------------------------------------------------
         * TRACKING NUMBER
         * ------------------------------------------------------
         */
        if (
            $this->oldTrackingNumber !== null &&
            $this->oldTrackingNumber !==
                $this->order->tracking_number
        ) {
            $changes[] =
                'Tracking number has been updated.';
        }

        /*
         * ------------------------------------------------------
         * ADMIN NOTE
         * ------------------------------------------------------
         */
        if (
            $this->oldAdminNote !== null &&
            $this->oldAdminNote !==
                $this->order->admin_note
        ) {
            $changes[] =
                'A note was added or updated on your order.';
        }

        /*
         * ------------------------------------------------------
         * FALLBACK
         * ------------------------------------------------------
         */
        if (empty($changes)) {
            $changes[] =
                'Your order has been updated.';
        }

        $orderNumber =
            $this->order->order_number
            ?? $this->order->id;

        return [
            'type' =>
                'order_updated',

            'title' =>
                'Order Updated',

            'message' =>
                implode(
                    ' ',
                    $changes
                ),

            'order_id' =>
                $this->order->id,

            'order_number' =>
                $orderNumber,

            'customer_id' =>
                $this->order->user_id,

            'status' =>
                $this->order->status,

            'payment_status' =>
                $this->order->payment_status,

            'tracking_number' =>
                $this->order->tracking_number,

            'total_amount' =>
                (float) $this->order->total_amount,

            'action_url' =>
                '/customer/orders/' .
                $this->order->id,

            'recipient_type' =>
                'customer',

            'created_at' =>
                now()->toISOString(),
        ];
    }

    /**
     * ==========================================================
     * ARRAY
     * ==========================================================
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * ==========================================================
     * FORMAT STATUS
     * ==========================================================
     */
    protected function formatStatus(
        ?string $status
    ): string {
        if (!$status) {
            return 'Unknown';
        }

        return ucwords(
            str_replace(
                '_',
                ' ',
                strtolower($status)
            )
        );
    }
}

