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
        Schema::table('merchant_product_groups', function (Blueprint $table) {
            $table->string('group_banner_video_link')->nullable()->after('group_banner_path');
            $table->boolean('align_banner_to_product_cards')->default(false)->after('group_banner_video_link');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_product_groups', function (Blueprint $table) {
            $table->dropColumn([
                'group_banner_video_link',
                'align_banner_to_product_cards'
            ]);
        });
    }
};
