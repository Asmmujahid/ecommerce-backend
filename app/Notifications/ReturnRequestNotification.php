<?php

namespace App\Notifications;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReturnRequestNotification extends Notification
{
    use Queueable;

    /**
     * ==========================================================
     * PROPERTIES
     * ==========================================================
     */

    protected ReturnRequest $returnRequest;

    protected string $action;

    /**
     * ==========================================================
     * CONSTRUCTOR
     * ==========================================================
     *
     * $action:
     *
     * submitted
     * updated
     */
    public function __construct(
        ReturnRequest $returnRequest,
        string $action = 'submitted'
    ) {
        $this->returnRequest = $returnRequest;
        $this->action = $action;

        /*
         * Load all relationships required
         * by the notification.
         */
        $this->returnRequest->loadMissing([
            'user',
            'order',
            'orderItem',
            'orderItem.product',
            'orderItem.product.vendor',
            'orderItem.product.vendor.user',
            'orderItem.productVariant',
        ]);
    }

    /**
     * ==========================================================
     * CHANNELS
     * ==========================================================
     *
     * Database notification is required for
     * the bell icon.
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
        $recipientType = $this->getRecipientType(
            $notifiable
        );

        $returnRequestId =
            $this->returnRequest->id;

        $orderId =
            $this->returnRequest->order_id;

        $orderItemId =
            $this->returnRequest->order_item_id;

        $orderNumber =
            $this->getOrderNumber();

        $customerId =
            $this->returnRequest->user_id;

        $customerName =
            $this->returnRequest->user?->name
            ?? 'Customer';

        $reason =
            $this->returnRequest->reason;

        $status =
            $this->returnRequest->status;

        $product =
            $this->returnRequest
                ->orderItem
                ?->product;

        $productId =
            $product?->id;

        $productName =
            $product?->name
            ?? 'Product';

        /*
         * ======================================================
         * CUSTOMER
         * ======================================================
         *
         * Customer receives this when:
         *
         * admin/seller approves or rejects
         * the return request.
         */
        if (
            $recipientType === 'customer'
            && $this->action === 'updated'
        ) {
            return [
                'type' =>
                    'return_request',

                'event' =>
                    'return_request_updated',

                'title' =>
                    'Return Request Updated',

                'message' =>
                    'Your return request for Order #' .
                    $orderNumber .
                    ' has been ' .
                    $this->formatStatus($status) .
                    '.',

                'return_request_id' =>
                    $returnRequestId,

                'order_id' =>
                    $orderId,

                'order_number' =>
                    $orderNumber,

                'order_item_id' =>
                    $orderItemId,

                'reason' =>
                    $reason,

                'status' =>
                    $status,

                'customer_id' =>
                    $customerId,

                'customer_name' =>
                    $customerName,

                'product_id' =>
                    $productId,

                'product_name' =>
                    $productName,

                'recipient_type' =>
                    'customer',

                'action' =>
                    'updated',

                'action_url' =>
                    '/customer/return-requests/' .
                    $returnRequestId,

                'created_at' =>
                    now()->toISOString(),
            ];
        }

        /*
         * ======================================================
         * SELLER
         * ======================================================
         *
         * Seller receives notification when
         * customer creates a return request.
         */
        if (
            $recipientType === 'seller'
            && $this->action === 'submitted'
        ) {
            return [
                'type' =>
                    'return_request',

                'event' =>
                    'return_request_submitted',

                'title' =>
                    'New Return Request',

                'message' =>
                    'A customer has submitted a return request for Order #' .
                    $orderNumber .
                    '.',

                'return_request_id' =>
                    $returnRequestId,

                'order_id' =>
                    $orderId,

                'order_number' =>
                    $orderNumber,

                'order_item_id' =>
                    $orderItemId,

                'reason' =>
                    $reason,

                'status' =>
                    $status,

                'customer_id' =>
                    $customerId,

                'customer_name' =>
                    $customerName,

                'product_id' =>
                    $productId,

                'product_name' =>
                    $productName,

                'recipient_type' =>
                    'seller',

                'action' =>
                    'submitted',

                'action_url' =>
                    '/seller/return-requests/' .
                    $returnRequestId,

                'created_at' =>
                    now()->toISOString(),
            ];
        }

        /*
         * ======================================================
         * ADMIN
         * ======================================================
         *
         * Admin receives notification when
         * customer creates a return request.
         */
        if (
            $recipientType === 'admin'
            && $this->action === 'submitted'
        ) {
            return [
                'type' =>
                    'return_request',

                'event' =>
                    'return_request_submitted',

                'title' =>
                    'New Return Request',

                'message' =>
                    'A new return request has been submitted for Order #' .
                    $orderNumber .
                    '.',

                'return_request_id' =>
                    $returnRequestId,

                'order_id' =>
                    $orderId,

                'order_number' =>
                    $orderNumber,

                'order_item_id' =>
                    $orderItemId,

                'reason' =>
                    $reason,

                'status' =>
                    $status,

                'customer_id' =>
                    $customerId,

                'customer_name' =>
                    $customerName,

                'product_id' =>
                    $productId,

                'product_name' =>
                    $productName,

                'recipient_type' =>
                    'admin',

                'action' =>
                    'submitted',

                'action_url' =>
                    '/admin/return-requests/' .
                    $returnRequestId,

                'created_at' =>
                    now()->toISOString(),
            ];
        }

        /*
         * ======================================================
         * FALLBACK
         * ======================================================
         *
         * This prevents an empty database notification.
         */
        return [
            'type' =>
                'return_request',

            'event' =>
                'return_request',

            'title' =>
                'Return Request Notification',

            'message' =>
                'There is an update regarding return request #' .
                $returnRequestId .
                '.',

            'return_request_id' =>
                $returnRequestId,

            'order_id' =>
                $orderId,

            'order_number' =>
                $orderNumber,

            'order_item_id' =>
                $orderItemId,

            'reason' =>
                $reason,

            'status' =>
                $status,

            'customer_id' =>
                $customerId,

            'customer_name' =>
                $customerName,

            'product_id' =>
                $productId,

            'product_name' =>
                $productName,

            'recipient_type' =>
                $recipientType,

            'action' =>
                $this->action,

            'action_url' =>
                $this->getActionUrl(
                    $recipientType,
                    $returnRequestId
                ),

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
        return $this->toDatabase(
            $notifiable
        );
    }

    /**
     * ==========================================================
     * MAIL
     * ==========================================================
     *
     * Currently database notification is used for
     * the bell icon.
     *
     * If you later want email notifications,
     * add 'mail' to via().
     */
    public function toMail(object $notifiable): MailMessage
    {
        $recipientType =
            $this->getRecipientType(
                $notifiable
            );

        $orderNumber =
            $this->getOrderNumber();

        $returnRequestId =
            $this->returnRequest->id;

        $status =
            $this->formatStatus(
                $this->returnRequest->status
            );

        if (
            $recipientType === 'customer'
        ) {
            return (new MailMessage)
                ->subject(
                    'Return Request Updated'
                )
                ->greeting(
                    'Hello ' .
                    ($notifiable->name ?? 'Customer') .
                    ','
                )
                ->line(
                    'Your return request has been updated.'
                )
                ->line(
                    'Order Number: #' .
                    $orderNumber
                )
                ->line(
                    'Current Status: ' .
                    $status
                )
                ->action(
                    'View Return Request',
                    url(
                        '/customer/return-requests/' .
                        $returnRequestId
                    )
                );
        }

        return (new MailMessage)
            ->subject(
                'New Return Request'
            )
            ->greeting(
                'Hello ' .
                ($notifiable->name ?? 'User') .
                ','
            )
            ->line(
                'A new return request has been submitted.'
            )
            ->line(
                'Order Number: #' .
                $orderNumber
            )
            ->line(
                'Current Status: ' .
                $status
            );
    }

    /**
     * ==========================================================
     * GET RECIPIENT TYPE
     * ==========================================================
     */
    protected function getRecipientType(
        object $notifiable
    ): string {
        /*
         * Customer
         */
        if (
            (int) $notifiable->id ===
            (int) $this->returnRequest->user_id
        ) {
            return 'customer';
        }

        /*
         * Admin
         */
        if (
            method_exists(
                $notifiable,
                'hasRole'
            )
        ) {
            try {
                if (
                    $notifiable->hasRole(
                        'admin'
                    )
                ) {
                    return 'admin';
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        /*
         * Seller
         */
        if (
            method_exists(
                $notifiable,
                'hasRole'
            )
        ) {
            try {
                if (
                    $notifiable->hasRole(
                        'seller'
                    )
                ) {
                    return 'seller';
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return 'admin';
    }

    /**
     * ==========================================================
     * ACTION URL
     * ==========================================================
     */
    protected function getActionUrl(
        string $recipientType,
        int|string $returnRequestId
    ): string {
        if (
            $recipientType === 'customer'
        ) {
            return '/customer/return-requests/' .
                $returnRequestId;
        }

        if (
            $recipientType === 'seller'
        ) {
            return '/seller/return-requests/' .
                $returnRequestId;
        }

        return '/admin/return-requests/' .
            $returnRequestId;
    }

    /**
     * ==========================================================
     * ORDER NUMBER
     * ==========================================================
     */
    protected function getOrderNumber(): string
    {
        $orderNumber =
            $this->returnRequest
                ->order
                ?->order_number;

        if (
            $orderNumber !== null &&
            $orderNumber !== ''
        ) {
            return (string) $orderNumber;
        }

        return (string)
            $this->returnRequest->order_id;
    }

    /**
     * ==========================================================
     * FORMAT STATUS
     * ==========================================================
     */
    protected function formatStatus(
        ?string $status
    ): string {
        if (
            !$status
        ) {
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