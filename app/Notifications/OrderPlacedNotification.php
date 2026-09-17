<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification
{
    use Queueable;

    protected Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;

        $this->order->loadMissing([
            'user',
            'items.product',
            'items.productVariant',
        ]);
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
     * DATABASE
     * ==========================================================
     */
    public function toDatabase(object $notifiable): array
    {
        /*
        |--------------------------------------------------------------------------
        | DEFAULT CUSTOMER
        |--------------------------------------------------------------------------
        */

        $recipientType =
            'customer';

        $title =
            'Order Placed Successfully';

        $message =
            'Your order #' .
            $this->order->order_number .
            ' has been placed successfully.';

        $actionUrl =
            '/customer/orders/' .
            $this->order->id;

        /*
        |--------------------------------------------------------------------------
        | ADMIN
        |--------------------------------------------------------------------------
        */

        if (
            method_exists($notifiable, 'hasRole') &&
            $notifiable->hasRole('admin')
        ) {
            $recipientType =
                'admin';

            $title =
                'New Order Received';

            $message =
                'New order #' .
                $this->order->order_number .
                ' has been placed.';

            $actionUrl =
                '/admin/orders/' .
                $this->order->id;
        }

        /*
        |--------------------------------------------------------------------------
        | SELLER
        |--------------------------------------------------------------------------
        */

        elseif (
            method_exists($notifiable, 'hasRole') &&
            $notifiable->hasRole('seller')
        ) {
            $recipientType =
                'seller';

            $title =
                'New Order Received';

            $message =
                'Order #' .
                $this->order->order_number .
                ' contains your product.';

            $actionUrl =
                '/seller/orders/' .
                $this->order->id;
        }

        return [
            'type' =>
                'order_placed',

            'title' =>
                $title,

            'message' =>
                $message,

            'order_id' =>
                $this->order->id,

            'order_number' =>
                $this->order->order_number,

            'customer_id' =>
                $this->order->user_id,

            'customer_name' =>
                $this->order->user?->name,

            'status' =>
                $this->order->status,

            'payment_status' =>
                $this->order->payment_status,

            'total_amount' =>
                (float) $this->order->total_amount,

            'recipient_type' =>
                $recipientType,

            'action_url' =>
                $actionUrl,
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
}

