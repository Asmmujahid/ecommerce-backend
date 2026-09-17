<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification
{
    /**
     * Payment instance.
     */
    protected Payment $payment;

    /**
     * =====================================================
     * CONSTRUCTOR
     * =====================================================
     */
    public function __construct(
        Payment $payment
    ) {
        /*
         * Make sure required relationships
         * are available.
         */
        $this->payment =
            $payment->loadMissing([
                'user',
                'order',
            ]);
    }

    /**
     * =====================================================
     * CHANNELS
     * =====================================================
     */
    public function via(
        object $notifiable
    ): array {
        return [
            'database',
            'mail',
        ];
    }

    /**
     * =====================================================
     * DATABASE NOTIFICATION
     * =====================================================
     */
    public function toArray(
        object $notifiable
    ): array {
        return [
            'type' =>
                'payment_received',

            'title' =>
                'Payment Received',

            'message' =>
                'Your payment for Order #' .
                $this->payment->order_id .
                ' has been received successfully.',

            'payment_id' =>
                $this->payment->id,

            'order_id' =>
                $this->payment->order_id,

            'amount' =>
                (float) $this->payment->amount,

            'payment_method' =>
                $this->payment->payment_method,

            'transaction_id' =>
                $this->payment->transaction_id,

            'status' =>
                $this->payment->status,

            'paid_at' =>
                $this->payment->paid_at?->toDateTimeString(),
        ];
    }

    /**
     * =====================================================
     * EMAIL NOTIFICATION
     * =====================================================
     */
    public function toMail(
        object $notifiable
    ): MailMessage {
        return (new MailMessage)
            ->subject(
                'Payment Received Successfully'
            )
            ->greeting(
                'Hello ' .
                (
                    $notifiable->name
                    ?? 'Customer'
                ) .
                ','
            )
            ->line(
                'Your payment has been received successfully.'
            )
            ->line(
                'Payment Details:'
            )
            ->line(
                'Order ID: #' .
                $this->payment->order_id
            )
            ->line(
                'Amount: Rs. ' .
                number_format(
                    (float) $this->payment->amount,
                    2
                )
            )
            ->line(
                'Payment Method: ' .
                strtoupper(
                    $this->payment->payment_method
                )
            )
            ->line(
                'Transaction ID: ' .
                (
                    $this->payment->transaction_id
                    ?? 'N/A'
                )
            )
            ->line(
                'Status: ' .
                ucfirst(
                    $this->payment->status
                )
            )
            ->action(
                'View Order',
                url(
                    '/customer/orders/' .
                    $this->payment->order_id
                )
            )
            ->line(
                'Thank you for shopping with us!'
            );
    }
}

