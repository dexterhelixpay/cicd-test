<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShopifyCustomImagesToSubscribedAndOrderedProductsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->json('shopify_custom_images')->nullable()->after('images');
        });

        Schema::table('ordered_products', function (Blueprint $table) {
            $table->json('shopify_custom_images')->nullable()->after('images');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->dropColumn('images');
        });

        Schema::table('ordered_products', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
}
