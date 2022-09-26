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
        Schema::table('merchant_recurrences', function (Blueprint $table) {
            $table->index(['merchant_id']);
        });

        Schema::table('product_recurrences', function (Blueprint $table) {
            $table->index(['product_id']);
        });

        Schema::table('product_option_values', function (Blueprint $table) {
            $table->index(['product_option_id', 'value']);
            $table->dropIndex(['value']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_recurrences', function (Blueprint $table) {
            $table->dropIndex(['merchant_id']);
        });

        Schema::table('product_recurrences', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });

        Schema::table('product_option_values', function (Blueprint $table) {
            $table->dropIndex(['product_option_id', 'value']);
            $table->index(['value']);
        });
    }
};
