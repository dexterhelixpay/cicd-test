<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsMembershipToSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('is_membership')->after('is_shopify_booking')->default(false);
        });

        Schema::table('ordered_products', function (Blueprint $table) {
            $table->boolean('is_membership')->after('is_shippable')->default(false);
        });

        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->boolean('is_membership')->after('is_shippable')->default(false);
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
            $table->dropColumn('is_membership');
        });

        Schema::table('ordered_products', function (Blueprint $table) {
            $table->dropColumn('is_membership');
        });

        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->dropColumn('is_membership');
        });
    }
}
