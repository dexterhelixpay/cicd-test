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
        Schema::table('merchant_product_groups', function (Blueprint $table) {
            $table->json('storefront_headline_css')->nullable()->after('storefront_headline_text');
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
            $table->dropColumn('storefront_headline_css');
        });
    }
};
