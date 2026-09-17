<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCancelledNotification extends Notification
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
            'mail',
        ];
    }

    /**
     * ==========================================================
     * DATABASE
     * ==========================================================
     */
    public function toDatabase(object $notifiable): array
    {
        $title =
            'Order Cancelled';

        $message =
            'Your order #' .
            $this->order->order_number .
            ' has been cancelled.';

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
            $title =
                'Customer Cancelled Order';

            $message =
                'Customer ' .
                ($this->order->user?->name ??
                    'Unknown customer') .
                ' cancelled order #' .
                $this->order->order_number .
                '.';

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
            $title =
                'Order Cancelled';

            $message =
                'Customer cancelled order #' .
                $this->order->order_number .
                ' containing your product.';

            $actionUrl =
                '/seller/orders/' .
                $this->order->id;
        }

        return [
            'type' =>
                'order_cancelled',

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

            'action_url' =>
                $actionUrl,

            'recipient_type' =>
                $notifiable->hasRole('admin')
                    ? 'admin'
                    : (
                        $notifiable->hasRole('seller')
                            ? 'seller'
                            : 'customer'
                    ),
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
     * MAIL
     * ==========================================================
     */
    public function toMail(object $notifiable): MailMessage
    {
        if (
            method_exists($notifiable, 'hasRole') &&
            $notifiable->hasRole('admin')
        ) {
            $subject =
                'Customer Cancelled Order';

            $message =
                'A customer has cancelled an order.';

            $url =
                url(
                    '/admin/orders/' .
                    $this->order->id
                );
        }

        elseif (
            method_exists($notifiable, 'hasRole') &&
            $notifiable->hasRole('seller')
        ) {
            $subject =
                'Customer Cancelled Order';

            $message =
                'Customer cancelled an order containing your product.';

            $url =
                url(
                    '/seller/orders/' .
                    $this->order->id
                );
        }

        else {
            $subject =
                'Order Cancelled';

            $message =
                'Your order has been cancelled.';

            $url =
                url(
                    '/customer/orders/' .
                    $this->order->id
                );
        }

        return (new MailMessage)
            ->subject($subject)

            ->greeting(
                'Hello ' .
                ($notifiable->name ?? 'User') .
                ','
            )

            ->line($message)

            ->line(
                'Order Number: #' .
                $this->order->order_number
            )

            ->line(
                'Order Status: ' .
                ucfirst(
                    $this->order->status
                )
            )

            ->line(
                'Payment Status: ' .
                ucfirst(
                    $this->order->payment_status
                )
            )

            ->line(
                'Total Amount: Rs. ' .
                number_format(
                    (float) $this->order->total_amount,
                    2
                )
            )

            ->action(
                'View Order',
                $url
            )

            ->line(
                'Thank you for using our e-commerce platform.'
            );
    }
}

