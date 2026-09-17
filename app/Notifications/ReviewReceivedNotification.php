<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewReceivedNotification extends Notification
{
    protected Review $review;

    /**
     * =====================================================
     * CONSTRUCTOR
     * =====================================================
     */
    public function __construct(Review $review)
    {
        $this->review = $review;
    }

    /**
     * =====================================================
     * NOTIFICATION CHANNELS
     * =====================================================
     */
    public function via(object $notifiable): array
    {
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
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'review_received',

            'title' => 'New Product Review',

            'message' =>
                'A customer has submitted a new review for your product.',

            'review_id' =>
                $this->review->id,

            'product_id' =>
                $this->review->product_id,

            'product_name' =>
                $this->review->product?->name,

            'customer_id' =>
                $this->review->user_id,

            'customer_name' =>
                $this->review->user?->name,

            'rating' =>
                $this->review->rating,

            'comment' =>
                $this->review->comment,

            'status' =>
                $this->review->status,
        ];
    }

    /**
     * =====================================================
     * EMAIL NOTIFICATION
     * =====================================================
     */
    public function toMail(object $notifiable): MailMessage
    {
        $roleName = 'User';

        try {
            if (
                method_exists(
                    $notifiable,
                    'hasRole'
                )
            ) {
                if (
                    $notifiable->hasRole('admin')
                ) {
                    $roleName = 'Admin';
                } elseif (
                    $notifiable->hasRole('seller')
                ) {
                    $roleName = 'Seller';
                }
            }
        } catch (\Throwable $e) {
            // Ignore role lookup errors.
        }

        return (new MailMessage)
            ->subject(
                'New Product Review Received'
            )

            ->greeting(
                'Hello ' .
                ($notifiable->name ?? $roleName) .
                ','
            )

            ->line(
                'A customer has submitted a new review.'
            )

            ->line(
                'Product: ' .
                (
                    $this->review->product?->name
                    ?? 'N/A'
                )
            )

            ->line(
                'Customer: ' .
                (
                    $this->review->user?->name
                    ?? 'N/A'
                )
            )

            ->line(
                'Rating: ' .
                $this->review->rating .
                ' / 5'
            )

            ->line(
                'Review:'
            )

            ->line(
                $this->review->comment
                ?? 'No comment provided.'
            )

            ->action(
                'View Reviews',
                url('/seller/reviews')
            )

            ->line(
                'Thank you for using our marketplace.'
            );
    }
}