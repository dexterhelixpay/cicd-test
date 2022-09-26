<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscountTypeAndDiscountValueToProductRecurrencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_recurrences', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_type_id')->nullable()->after('original_price')->index();
            $table->double('discount_value', 10, 2)->nullable()->after('discount_type_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_recurrences', function (Blueprint $table) {
            $table->dropColumn('discount_type_id');
            $table->dropColumn('discount_value');
        });
    }
}
