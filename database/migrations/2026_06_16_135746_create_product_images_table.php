<?php



use Illuminate\Database\Migrations\Migration;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\Schema;



return new class extends Migration

{

    public function up(): void

    {

        Schema::create('product_images', function (Blueprint $table) {

            $table->id();



            // Relationship

            $table->foreignId('product_id')

                  ->constrained()

                  ->onDelete('cascade');



            // Image path

            $table->string('image');



            // Optional: sort order for gallery

            $table->integer('sort_order')->default(0);



            $table->timestamps();

        });

    }



    public function down(): void

    {

        Schema::dropIfExists('product_images');

    }

};