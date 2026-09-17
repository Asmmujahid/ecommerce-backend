<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // Order relation
            $table->foreignId('order_id')
                ->constrained()
                ->onDelete('cascade');

            // Product relation
            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');

                // Vendor snapshot 
                $table->foreignId('vendor_id') 
                ->constrained('vendors') 
                ->restrictOnDelete();

                $table->string('product_owner_type') 
                ->default('vendor')
                 ->index();

            // Variant (optional)
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->onDelete('cascade');

            // Quantity purchased
            $table->integer('quantity');

            // Price at purchase time (important)
            $table->decimal('price', 10, 2);

            // Total (price * quantity)
            $table->decimal('total', 10, 2);

            $table->timestamps();

             $table->index('order_id');
             $table->index('product_id'); 
             $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};