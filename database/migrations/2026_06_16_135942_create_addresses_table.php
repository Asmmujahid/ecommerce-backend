<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            // User relation
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Receiver details
            $table->string('name');
            $table->string('phone');

            // Address details
            $table->text('address_line');
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country');

            // Type: home / office
            $table->enum('type', ['home', 'office'])->default('home');

            // Default address
            $table->boolean('is_default')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};