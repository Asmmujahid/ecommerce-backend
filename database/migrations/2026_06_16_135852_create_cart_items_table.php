<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            // Cart relation
            $table->foreignId('cart_id')
                ->constrained()
                ->onDelete('cascade');

            // Product relation
            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');

            // Variant (optional)
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->onDelete('cascade');

            // Quantity in cart
            $table->integer('quantity')->default(1);

            // Price snapshot (important for checkout history)
            $table->decimal('price', 10, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};