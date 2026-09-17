<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductOutOfStockNotification extends Notification
{
    use Queueable;

    protected Product $product;

    /**
     * =====================================================
     * CONSTRUCTOR
     * =====================================================
     */
    public function __construct(
        Product $product
    ) {
        $this->product = $product;
    }

    /**
     * =====================================================
     * NOTIFICATION CHANNELS
     * =====================================================
     *
     * Database notification is enough for the
     * notification panel.
     */
    public function via(
        object $notifiable
    ): array {
        return [
            'database',
        ];
    }

    /**
     * =====================================================
     * DATABASE NOTIFICATION
     * =====================================================
     */
    public function toDatabase(
        object $notifiable
    ): array {

        return [
            'type' =>
                'product_out_of_stock',

            'title' =>
                'Product Out of Stock',

            'message' =>
                'Product "' .
                ($this->product->name
                    ?? 'Unknown Product') .
                '" is now out of stock.',

            'product_id' =>
                $this->product->id,

            'product_name' =>
                $this->product->name,

            'sku' =>
                $this->product->sku,

            'stock' =>
                (int) $this->product->stock,

            'vendor_id' =>
                $this->product->vendor_id,

            'created_at' =>
                now()->toDateTimeString(),
        ];
    }

    /**
     * =====================================================
     * ARRAY REPRESENTATION
     * =====================================================
     */
    public function toArray(
        object $notifiable
    ): array {

        return [
            'type' =>
                'product_out_of_stock',

            'title' =>
                'Product Out of Stock',

            'message' =>
                'Product "' .
                ($this->product->name
                    ?? 'Unknown Product') .
                '" is now out of stock.',

            'product_id' =>
                $this->product->id,

            'product_name' =>
                $this->product->name,

            'sku' =>
                $this->product->sku,

            'stock' =>
                (int) $this->product->stock,

            'vendor_id' =>
                $this->product->vendor_id,
        ];
    }
}