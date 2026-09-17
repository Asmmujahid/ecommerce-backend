<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | Primary Key
            |--------------------------------------------------------------------------
            */

            $table->uuid('id')->primary();

            /*
            |--------------------------------------------------------------------------
            | Notification Type
            |--------------------------------------------------------------------------
            |
            | Example:
            | App\Notifications\OrderPlacedNotification
            |
            */

            $table->string('type');

            /*
            |--------------------------------------------------------------------------
            | Notifiable User
            |--------------------------------------------------------------------------
            |
            | morphs() automatically creates:
            |
            | notifiable_type
            | notifiable_id
            |
            | and the required composite index.
            |
            | IMPORTANT:
            | Do NOT create another index manually.
            |
            */

            $table->morphs('notifiable');

            /*
            |--------------------------------------------------------------------------
            | Notification Data
            |--------------------------------------------------------------------------
            |
            | Stores the data returned from toArray().
            |
            */

            $table->text('data');

            /*
            |--------------------------------------------------------------------------
            | Read Status
            |--------------------------------------------------------------------------
            |
            | NULL     = unread
            | datetime = read
            |
            */

            $table->timestamp('read_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};