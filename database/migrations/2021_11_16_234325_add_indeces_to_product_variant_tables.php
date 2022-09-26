<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndecesToProductVariantTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_options', function (Blueprint $table) {
            $table->index('code');
        });

        Schema::table('product_option_values', function (Blueprint $table) {
            $table->index('value');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_options', function (Blueprint $table) {
            $table->dropIndex(['code']);
        });

        Schema::table('product_option_values', function (Blueprint $table) {
            $table->dropIndex(['value']);
        });
    }
}
