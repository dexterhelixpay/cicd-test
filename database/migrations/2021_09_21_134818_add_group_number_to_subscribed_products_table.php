<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGroupNumberToSubscribedProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->json('payment_schedule')->after('description');
            $table->unsignedInteger('group_number')->default(1)->after('total_price');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->dropColumn('payment_schedule', 'group_number');
        });
    }
}
