<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMembershipBannerSettingsToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->longText('membership_banner_path')->nullable()->after('card_text_color');
            $table->longText('membership_header_text')->nullable()->after('membership_banner_path');
            $table->longText('membership_subheader_text')->nullable()->after('membership_header_text');
            $table->json('membership_banner_border_config')->nullable()->after('membership_subheader_text');
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
                'membership_banner_path',
                'membership_header_text',
                'membership_subheader_text',
                'membership_banner_border_config'
            ]);
        });
    }
}
