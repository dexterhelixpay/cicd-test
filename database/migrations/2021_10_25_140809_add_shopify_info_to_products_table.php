<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShopifyInfoToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('shopify_info')->nullable()->after('description');
            $table->unsignedBigInteger('shopify_product_id')->after('shopify_info')->nullable();
            $table->boolean('is_shopify_product')->after('is_visible')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('shopify_info', 'shopify_product_id', 'is_shopify_product');
        });
    }
}
