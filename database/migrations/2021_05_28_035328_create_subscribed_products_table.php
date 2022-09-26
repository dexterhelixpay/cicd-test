<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscribedProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscribed_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id')->index();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->double('price', 10, 2)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->double('total_price', 10, 2)->nullable();
            $table->boolean('are_multiple_orders_allowed')->default(false);
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
        Schema::dropIfExists('subscribed_products');
    }
}
