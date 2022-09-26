<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVatAndConvenienceFeeToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->double('vat_amount', 10, 2)->after('bank_fee')->nullable();
            $table->double('convenience_fee', 10, 2)->after('bank_fee')->nullable();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->double('vat_amount', 10, 2)->after('bank_fee')->nullable();
            $table->double('convenience_fee', 10, 2)->after('vat_amount')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'vat_amount',
                'convenience_fee'
            ]);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'vat_amount',
                'convenience_fee'
            ]);
        });
    }
}
