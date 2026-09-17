<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();

            // User who requested return
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Related order
            $table->foreignId('order_id')
                ->constrained()
                ->onDelete('cascade');

            // Specific item in order
            $table->foreignId('order_item_id')
                ->constrained()
                ->onDelete('cascade');

            // Reason for return
            $table->text('reason');

            // Status tracking
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'refunded'
            ])->default('pending');

            // Refund amount (if approved)
            $table->decimal('refund_amount', 10, 2)->nullable();

            // Admin response note
            $table->text('admin_note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};