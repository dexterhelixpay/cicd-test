<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomShippingFeeToSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('subscriptions', 'custom_shipping_fee')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->double('custom_shipping_fee', 10, 2)->nullable()->after('shipping_fee');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        if (Schema::hasColumn('subscriptions', 'custom_shipping_fee')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('custom_shipping_fee');
            });
        }
    }
}
