<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTooltipTitleAndTooltipSubtitleToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->text('tooltip_title')
                ->after('payment_alert_subtitle')
                ->nullable();
            $table->longText('tooltip_subtitle')
                ->after('tooltip_title')
                ->nullable();
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
            $table->dropColumn('tooltip_title');
            $table->dropColumn('tooltip_subtitle');
        });
    }
}
