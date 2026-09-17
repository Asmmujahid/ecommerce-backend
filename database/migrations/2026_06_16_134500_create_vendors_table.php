<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();

            // Link vendor to user account
            // One user can have only one vendor/store
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Store details
            $table->string('store_name');
            $table->string('store_slug')->unique();
            $table->text('description')->nullable();

            // Branding
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();

            // Contact information
            $table->string('phone')->nullable();
            $table->text('address')->nullable();

            // Vendor status
            $table->boolean('status')->default(true);

            // One vendor/store per user
            $table->unique('user_id');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};

