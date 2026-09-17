<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();

            // Product reference
            $table->foreignId('product_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');

            // Variant reference (optional)
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->onDelete('cascade');

            // Stock movement
            $table->integer('quantity');

            // add / remove stock tracking
            $table->enum('type', ['in', 'out']);

            // optional note
            $table->string('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};