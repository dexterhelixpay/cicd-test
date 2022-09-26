<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupedMerchantProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('grouped_merchant_products', function (Blueprint $table) {
            $table->unsignedBigInteger('merchant_product_group_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('sort_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grouped_merchant_products');
    }
}
