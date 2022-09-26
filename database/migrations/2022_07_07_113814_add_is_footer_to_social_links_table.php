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
        Schema::table('social_links', function (Blueprint $table) {
            $table->string('label')->nullable()->change();
            $table->boolean('is_footer')->default(false)->after('is_visible');
            $table->unsignedInteger('sort_number')->default(1);
        });

        Schema::table('social_link_icons', function (Blueprint $table) {
            $table->boolean('is_footer')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('social_links', function (Blueprint $table) {
            $table->string('label')->change();
            $table->dropColumn(['is_footer', 'sort_number']);
        });

        Schema::table('social_link_icons', function (Blueprint $table) {
            $table->dropColumn('is_footer');
        });
    }
};
