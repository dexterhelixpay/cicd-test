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
            $table->string('storefront_headline_text')->nullable()->after('slug');
            $table->string('icon_path')->nullable()->after('storefront_headline_text');
            $table->string('video_banner')->nullable()->after('icon_path');
            $table->string('group_banner_path')->nullable()->after('video_banner');
            $table->string('description_title')->nullable()->after('group_banner_path');
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
                'storefront_headline_text',
                'icon_path',
                'video_banner',
                'group_banner',
                'description_title'
            ]);
        });
    }
};
