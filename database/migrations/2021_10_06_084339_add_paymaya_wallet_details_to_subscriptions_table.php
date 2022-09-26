<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymayaWalletDetailsToSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('paymaya_wallet_customer_name')->after('failure_redirect_url')->nullable();
            $table->string('paymaya_wallet_mobile_number')->after('paymaya_wallet_customer_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['paymaya_wallet_customer_name', 'paymaya_wallet_mobile_number']);
        });
    }
}
