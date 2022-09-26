<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddButtonBackgroundColorToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->text('button_background_color')->nullable()->after('header_background_color');
            $table->text('background_color')->nullable()->change();
            $table->text('header_background_color')->nullable()->change();
            $table->text('footer_background_color')->nullable()->change();
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
            $table->dropColumn('button_background_color');
            $table->string('background_color')->nullable()->change();
            $table->string('header_background_color')->nullable()->change();
            $table->string('footer_background_color')->nullable()->change();
        });
    }
}
