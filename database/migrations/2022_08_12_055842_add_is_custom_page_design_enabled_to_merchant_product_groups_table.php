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
            $table->boolean('is_custom_page_design_enabled')->default(false)->after('align_banner_to_product_cards');
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
            $table->dropColumn('is_custom_page_design_enabled');
        });
    }
};
