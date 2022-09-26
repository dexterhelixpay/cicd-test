<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGroupColorConfigToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('group_background_color')->nullable()->after('on_background_color');
            $table->string('group_highlight_color')->nullable()->after('group_background_color');
            $table->string('group_unhighlight_color')->nullable()->after('group_highlight_color');

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
                'group_background_color',
                'group_highlight_color',
                'group_unhighlight_color'
            ]);
        });
    }
}
