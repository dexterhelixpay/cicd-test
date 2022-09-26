<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShippingAndFulfillmentDaysToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->unsignedInteger('shipping_days_after_payment')->default(2)
                ->after('has_shippable_products');

            $table->unsignedInteger('fulfillment_days_after_payment')->default(2)
                ->after('has_digital_products');
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
            $table->dropColumn('shipping_days_after_payment', 'fulfillment_days_after_payment');
        });
    }
}
