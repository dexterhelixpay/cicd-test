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
        Schema::table('merchants', function (Blueprint $table) {
            $table->text('product_details_button_color')->nullable();
            $table->text('product_details_button_text')->nullable();

            $table->text('product_select_button_color')->nullable();
            $table->text('product_select_button_text')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn([
                'product_details_button_color',
                'product_details_button_text',
                'product_select_button_color',
                'product_select_button_text'
            ]);
        });
    }
};
