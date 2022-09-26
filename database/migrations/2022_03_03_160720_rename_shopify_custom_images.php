<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameShopifyCustomImages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ordered_products', function (Blueprint $table) {
            $table->renameColumn('shopify_custom_images', 'shopify_custom_links');
        });

        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->renameColumn('shopify_custom_images', 'shopify_custom_links');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ordered_products', function (Blueprint $table) {
            $table->renameColumn('shopify_custom_links', 'shopify_custom_images');
        });

        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->renameColumn('shopify_custom_links', 'shopify_custom_images');
        });
    }
}
