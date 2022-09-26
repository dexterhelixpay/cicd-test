<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymayaMidAndPwpKeysToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->renameColumn('paymaya_public_key', 'paymaya_vault_public_key');
            $table->renameColumn('paymaya_secret_key', 'paymaya_vault_secret_key');

            $table->string('paymaya_mid')->nullable()->after('card_discount');
            $table->string('paymaya_pwp_public_key')->nullable()->after('paymaya_secret_key');
            $table->string('paymaya_pwp_secret_key')->nullable()->after('paymaya_pwp_public_key');
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
            $table->renameColumn('paymaya_vault_public_key', 'paymaya_public_key');
            $table->renameColumn('paymaya_vault_secret_key', 'paymaya_secret_key');

            $table->dropColumn('paymaya_mid', 'paymaya_pwp_public_key', 'paymaya_pwp_secret_key');
        });
    }
}
