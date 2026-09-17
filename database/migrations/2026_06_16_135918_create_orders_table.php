<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // User who placed order
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Unique order tracking number
            $table->string('order_number')->unique();

            // Original amount before coupon
            $table->decimal('subtotal_amount', 10, 2)->default(0);

            // Coupon used for this order
            $table->foreignId('coupon_id')
                ->nullable()
                ->constrained('coupons')
                ->nullOnDelete();

            // Coupon code used
            $table->string('coupon_code')->nullable();

            // Discount amount
            $table->decimal('discount_amount', 10, 2)->default(0);

            // Final amount after discount
            $table->decimal('total_amount', 10, 2);

            // Order status
            $table->enum('status', [
                'pending',
                'processing',
                'shipped',
                'delivered',
                'cancelled'
            ])->default('pending');

            // Payment status
            $table->enum('payment_status', [
                'pending',
                'paid',
                'failed',
                'refunded'
            ])->default('pending');

            // Payment method
            $table->string('payment_method')->nullable();

            // Shipping address snapshot
            $table->text('shipping_address')->nullable();

            $table->string('tracking_number')->nullable();

            $table->text('admin_note')->nullable();

            $table->timestamps();

            $table->index('user_id'); 
            $table->index('status'); 
            $table->index('payment_status');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};