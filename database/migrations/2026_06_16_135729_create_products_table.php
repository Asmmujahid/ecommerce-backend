<?php



use Illuminate\Database\Migrations\Migration;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\Schema;



return new class extends Migration

{

    public function up(): void

    {

        Schema::create('products', function (Blueprint $table) {

            $table->id();



           $table->foreignId('category_id')

         ->constrained('categories')

           ->onDelete('cascade');



         $table->foreignId('brand_id')

          ->constrained('brands')

          ->onDelete('cascade');





          $table->foreignId('vendor_id')

      ->constrained('vendors')

      ->onDelete('cascade');

      
         $table->string('owner_type') 
         ->default('vendor') 
         ->index();

            // Product info

            $table->string('name');

            $table->string('slug')->unique();

            $table->string('sku')->unique();



            $table->text('short_description')->nullable();

            $table->longText('description')->nullable();



            // Pricing

            $table->decimal('price', 10, 2);

            $table->decimal('discount_price', 10, 2)->nullable();



            // Stock

            $table->integer('stock')->default(0);



            // Media

            $table->string('thumbnail')->nullable();



            // Status

            $table->boolean('status')->default(true);

            $table->boolean('featured')->default(false);

            $table->timestamps();

             $table->index('vendor_id');

        });

    }



    public function down(): void

    {

        Schema::dropIfExists('products');

    }

};