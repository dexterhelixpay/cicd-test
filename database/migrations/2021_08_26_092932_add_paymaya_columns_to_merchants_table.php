<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymayaColumnsToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('paymaya_mid')->nullable()->after('card_discount');
            $table->string('paymaya_public_key')->nullable()->after('paymaya_mid');
            $table->string('paymaya_secret_key')->nullable()->after('paymaya_public_key');
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
            $table->dropColumn('paymaya_mid', 'paymaya_public_key', 'paymaya_secret_key');
        });
    }
}
