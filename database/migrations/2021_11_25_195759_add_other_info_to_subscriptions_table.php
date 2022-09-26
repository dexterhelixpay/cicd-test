<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOtherInfoToSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('subscriptions', 'other_info')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->json('other_info')->nullable()->after('shipping_zip_code');
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
        if (Schema::hasColumn('subscriptions', 'other_info')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('other_info');
            });
        }
    }
}
