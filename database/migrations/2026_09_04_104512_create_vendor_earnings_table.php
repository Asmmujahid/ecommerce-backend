<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_earnings', function (Blueprint $table) {
            $table->id();

            // Vendor
            $table->foreignId('vendor_id')
                ->constrained('vendors')
                ->cascadeOnDelete();

            // Order
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // Order item
            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();

            // Vendor sale amount before commission
            $table->decimal(
                'gross_amount',
                12,
                2
            );

            // Marketplace commission percentage
            $table->decimal(
                'commission_rate',
                5,
                2
            )->default(10);

            // Admin/platform commission
            $table->decimal(
                'commission_amount',
                12,
                2
            )->default(0);

            // Vendor earning after commission
            $table->decimal(
                'net_amount',
                12,
                2
            );

            // Vendor payout status
            $table->enum('status', [
                'pending',
                'paid',
                'reversed',
            ])->default('pending');

            // When vendor was paid
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // One earning record per order item
            $table->unique(
                'order_item_id',
                'vendor_earnings_order_item_unique'
            );

            // Useful indexes
            $table->index([
                'vendor_id',
                'status',
            ]);

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_earnings');
    }
};