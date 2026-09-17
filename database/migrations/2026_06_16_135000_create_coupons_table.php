<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Vendor
            |--------------------------------------------------------------------------
            | NULL  = Admin coupon
            | Value = Vendor coupon
            |--------------------------------------------------------------------------
            */
            $table->foreignId('vendor_id')
                ->nullable()
                ->constrained('vendors')
                ->onDelete('cascade');

            /*
            |--------------------------------------------------------------------------
            | Admin Coupon Scope
            |--------------------------------------------------------------------------
            | admin_only   = Only admin-owned products
            | all_products = Admin + vendor products
            | NULL         = Vendor coupon
            |--------------------------------------------------------------------------
            */
            $table->enum('admin_coupon_scope', [
                'admin_only',
                'all_products',
            ])
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Coupon Code
            |--------------------------------------------------------------------------
            */
            $table->string('code')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Discount Type
            |--------------------------------------------------------------------------
            */
            $table->enum('type', [
                'percentage',
                'fixed',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Discount Value
            |--------------------------------------------------------------------------
            */
            $table->decimal('value', 10, 2);

            /*
            |--------------------------------------------------------------------------
            | Minimum Order Amount
            |--------------------------------------------------------------------------
            */
            $table->decimal('min_order_amount', 10, 2)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Maximum Discount
            |--------------------------------------------------------------------------
            | Mainly used for percentage coupons.
            | Fixed coupons should have NULL.
            |--------------------------------------------------------------------------
            */
            $table->decimal('max_discount', 10, 2)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Usage Control
            |--------------------------------------------------------------------------
            */
            $table->integer('usage_limit')
                ->nullable();

            $table->integer('used_count')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Coupon Validity
            |--------------------------------------------------------------------------
            */
            $table->date('start_date');

            $table->date('end_date');

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->boolean('status')
                ->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};