<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderDeliveredNotification extends Notification
{
    use Queueable;

    /**
     * The order.
     */
    protected Order $order;

    /**
     * Create notification.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;

        /*
         * Load relationships that may be needed
         * by the notification.
         */
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
     * DATABASE NOTIFICATION
     * ==========================================================
     */
    public function toDatabase(object $notifiable): array
    {
        $orderNumber =
            $this->order->order_number
            ?? $this->order->id;

        return [
            'type' =>
                'order_delivered',

            'title' =>
                'Order Delivered',

            'message' =>
                'Your order #' .
                $orderNumber .
                ' has been delivered successfully.',

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
     * ARRAY REPRESENTATION
     * ==========================================================
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * ==========================================================
     * MAIL NOTIFICATION
     * ==========================================================
     */
    public function toMail(object $notifiable): MailMessage
    {
        $orderNumber =
            $this->order->order_number
            ?? $this->order->id;

        return (new MailMessage)
            ->subject(
                'Order Delivered - #' .
                $orderNumber
            )

            ->greeting(
                'Hello ' .
                ($notifiable->name ?? 'Customer') .
                ','
            )

            ->line(
                'Your order has been delivered successfully.'
            )

            ->line(
                'Order Number: #' .
                $orderNumber
            )

            ->line(
                'Order Status: ' .
                $this->formatStatus(
                    $this->order->status
                )
            )

            ->line(
                'Payment Status: ' .
                $this->formatStatus(
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
                url(
                    '/customer/orders/' .
                    $this->order->id
                )
            )

            ->line(
                'Thank you for shopping with us.'
            );
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

