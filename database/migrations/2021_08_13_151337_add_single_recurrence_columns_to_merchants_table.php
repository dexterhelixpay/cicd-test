<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSingleRecurrenceColumnsToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('single_recurrence_title')->nullable();
            $table->string('single_recurrence_subtitle')->nullable();
            $table->string('single_recurrence_button_text')->nullable();
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
            $table->dropColumn([
                'single_recurrence_title',
                'single_recurrence_subtitle',
                'single_recurrence_button_text'
            ]);
        });
    }
}
