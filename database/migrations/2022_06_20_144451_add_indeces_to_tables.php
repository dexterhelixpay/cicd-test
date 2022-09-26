<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('customer_id');
            $table->index('payment_type_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('subscription_id');
            $table->index('payment_type_id');
            $table->index('payment_status_id');
            $table->index('order_status_id');
        });

        Schema::table('table_columns', function (Blueprint $table) {
            $table->index('table');
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
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['payment_type_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['subscription_id']);
            $table->dropIndex(['payment_type_id']);
            $table->dropIndex(['payment_status_id']);
            $table->dropIndex(['order_status_id']);
        });

        Schema::table('table_columns', function (Blueprint $table) {
            $table->dropIndex(['table']);
        });
    }
};
