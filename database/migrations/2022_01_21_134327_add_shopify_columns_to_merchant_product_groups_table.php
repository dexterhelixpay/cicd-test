<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShopifyColumnsToMerchantProductGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchant_product_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('shopify_collection_id')->after('name')->nullable();
            $table->json('shopify_info')->nullable()->after('shopify_collection_id');
            $table->boolean('is_shopify_group')->after('shopify_info');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_product_groups', function (Blueprint $table) {
            $table->dropColumn(['shopify_info', 'is_shopify_group']);
        });
    }
}
