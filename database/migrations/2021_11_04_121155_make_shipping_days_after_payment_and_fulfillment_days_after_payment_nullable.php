<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeShippingDaysAfterPaymentAndFulfillmentDaysAfterPaymentNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->unsignedInteger('shipping_days_after_payment')
                ->nullable()
                ->default(null)
                ->change();
            $table->unsignedInteger('fulfillment_days_after_payment')
                ->nullable()
                ->default(null)
                ->change();
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
            $table->unsignedInteger('shipping_days_after_payment')->default(2)
                ->nullable(false)
                ->change();
            $table->unsignedInteger('fulfillment_days_after_payment')->default(2)
                ->nullable(false)
                ->change();
        });
    }
}
