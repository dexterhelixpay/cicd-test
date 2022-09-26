<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymayaMerchantIdToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->unsignedBigInteger('paymaya_merchant_id')->nullable()->after('pricing_type_id');
            $table->dropColumn('paymaya_mid');
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
            $table->dropColumn('paymaya_merchant_id');
            $table->string('paymaya_mid')->nullable()->after('card_discount');
        });
    }
}
