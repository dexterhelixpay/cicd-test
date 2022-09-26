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
        Schema::table('merchants', function (Blueprint $table) {
            $table->boolean('is_logo_visible')->default(true)->after('logo_image_path');
            $table->boolean('is_svg_logo_visible')->default(true)->after('logo_svg_path');
            $table->boolean('is_home_banner_visible')->default(true)->after('home_banner_path');
            $table->boolean('is_favicon_visible')->default(true)->after('favicon_path');
            $table->boolean('is_membership_banner_visible')->default(true)->after('membership_banner_path');
            $table->boolean('is_members_video_banner_visible')->default(true)->after('members_video_banner');
            $table->boolean('is_products_video_banner_visible')->default(true)->after('products_video_banner');
            $table->boolean('is_customer_promo_image_visible')->default(true)->after('customer_promo_image_path');
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
                'is_logo_visible',
                'is_svg_logo_visible',
                'is_home_banner_visible',
                'is_favicon_visible',
                'is_membership_banner_visible',
                'is_members_video_banner_visible',
                'is_products_video_banner_visible',
                'is_customer_promo_image_visible'
            ]);
        });
    }
};
