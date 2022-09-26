<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCorporateInfoToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->longText('invoice_corporate_info')->nullable()->after('is_delivery_faqs_enabled');
            $table->boolean('has_corporate_info_on_invoice')->default(false)->after('invoice_corporate_info');
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
            $table->dropColumn(['invoice_corporate_info', 'has_corporate_info_on_invoice']);
        });
    }
}
