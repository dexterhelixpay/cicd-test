<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_group_description_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_product_group_id')->index();
            $table->text('description');
            $table->string('icon_path')->nullable();
            $table->string('emoji')->nullable();
            $table->unsignedInteger('sort_number')->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_group_description_items');
    }
};
