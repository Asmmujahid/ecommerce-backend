<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();

            // Banner title (optional)
            $table->string('title')->nullable();

            // Image path
            $table->string('image');

            // Redirect link when clicked
            $table->string('link')->nullable();

            // Active / inactive banner
            $table->boolean('status')->default(true);

            // Order position (slider order)
            $table->integer('position')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};