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
            $table->dropColumn('is_svg_logo_visible');
            $table->dropColumn('is_favicon_visible');
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
            $table->boolean('is_svg_logo_visible')->default(true)->after('logo_svg_path');
            $table->boolean('is_favicon_visible')->default(true)->after('favicon_path');
        });
    }
};
