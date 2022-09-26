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
        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->unsignedInteger('max_discounted_order_count')->nullable()
                ->after('payment_schedule');
            $table->double('discounted_price', 10, 2)->nullable()->after('price');
            $table->double('total_discounted_price', 10, 2)->nullable()->after('discounted_price');
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
            $table->dropColumn([
                'max_discounted_order_count',
                'discounted_price',
                'total_discounted_price',
            ]);
        });
    }
};
