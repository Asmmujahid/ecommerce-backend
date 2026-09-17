<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            // User who wrote the review
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Product being reviewed
            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');

            // Rating (1 to 5)
            $table->tinyInteger('rating')->unsigned();

            // Review comment
            $table->text('comment')->nullable();

            // Review status
          $table->enum('status', ['active', 'hidden', 'flagged'])->default('active');

            $table->timestamps();

            // One review per user per product
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};