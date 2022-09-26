<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRecurrenceMonthToMerchantRecurrencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchant_recurrences', function (Blueprint $table) {
            $table->unsignedBigInteger('recurrence_month')->nullable()->after('recurrence_day');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_recurrences', function (Blueprint $table) {
            $table->dropColumn('recurrence_month');
        });
    }
}
