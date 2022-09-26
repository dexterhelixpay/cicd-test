<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSortNumberIndeces extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchant_description_items', function (Blueprint $table) {
            $table->index('sort_number');
        });

        Schema::table('merchant_recurrences', function (Blueprint $table) {
            $table->index('sort_number');
        });

        Schema::table('merchant_product_groups', function (Blueprint $table) {
            $table->index('sort_number');
        });

        Schema::table('grouped_merchant_products', function (Blueprint $table) {
            $table->index('sort_number');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('sort_number');
        });

        Schema::table('product_description_items', function (Blueprint $table) {
            $table->index('sort_number');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->index('sort_number');
        });

        Schema::table('product_recurrences', function (Blueprint $table) {
            $table->index('sort_number');
        });

        Schema::table('product_options', function (Blueprint $table) {
            $table->index('sort_number');
        });

        Schema::table('product_option_values', function (Blueprint $table) {
            $table->index('sort_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_description_items', function (Blueprint $table) {
            $table->dropIndex('merchant_description_items_sort_number_index');
        });

        Schema::table('merchant_recurrences', function (Blueprint $table) {
            $table->dropIndex('merchant_recurrences_sort_number_index');
        });

        Schema::table('merchant_product_groups', function (Blueprint $table) {
            $table->dropIndex('merchant_product_groups_sort_number_index');
        });

        Schema::table('grouped_merchant_products', function (Blueprint $table) {
            $table->dropIndex('grouped_merchant_products_sort_number_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_sort_number_index');
        });

        Schema::table('product_description_items', function (Blueprint $table) {
            $table->dropIndex('product_description_items_sort_number_index');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex('product_images_sort_number_index');
        });

        Schema::table('product_recurrences', function (Blueprint $table) {
            $table->dropIndex('product_recurrences_sort_number_index');
        });

        Schema::table('product_options', function (Blueprint $table) {
            $table->dropIndex('product_options_sort_number_index');
        });

        Schema::table('product_option_values', function (Blueprint $table) {
            $table->dropIndex('product_option_values_sort_number_index');
        });
    }
}
