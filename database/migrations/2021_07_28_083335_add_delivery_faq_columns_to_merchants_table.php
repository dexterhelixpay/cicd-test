<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeliveryFaqColumnsToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->longText('delivery_faqs')->nullable()->after('is_faqs_enabled');
            $table->string('delivery_faqs_title')->nullable()->after('delivery_faqs');
            $table->boolean('is_delivery_faqs_enabled')->default(true)->after('delivery_faqs_title');
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
            $table->dropColumn(['delivery_faqs', 'delivery_faqs_title', 'is_delivery_faqs_enabled']);
        });
    }
}
