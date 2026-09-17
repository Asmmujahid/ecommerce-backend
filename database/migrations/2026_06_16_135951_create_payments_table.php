<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Customer
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Order
            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            // Payment information
            $table->string('transaction_id')
                ->nullable()
                ->unique();

            $table->decimal(
                'amount',
                10,
                2
            );

            // Payment method
            $table->enum(
                'payment_method',
                [
                    'cod',
                    'bank_transfer',
                    'card',
                    'online',
                    'wallet',
                ]
            );

            // Payment status
            $table->enum(
                'status',
                [
                    'pending',
                    'paid',
                    'failed',
                    'refunded',
                ]
            )->default('pending');

            $table->timestamp(
                'paid_at'
            )->nullable();

            $table->timestamps();

            $table->index('user_id'); 
            $table->index('order_id'); 
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payments'
        );
    }
};