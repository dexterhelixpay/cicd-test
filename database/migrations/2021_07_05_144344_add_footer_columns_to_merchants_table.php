<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFooterColumnsToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('tagline')->nullable()
                ->after('subdomain');

            $table->string('footer_background_color')->nullable()
                ->after('on_background_color');

            $table->string('footer_text_color')->default('#2F2F2F')
                ->after('footer_background_color');
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
            $table->dropColumn('tagline', 'footer_background_color', 'footer_text_color');
        });
    }
}
