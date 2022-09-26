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
        if (!Schema::hasColumn('merchants', 'members_page_text_color')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->text('members_page_text_color')
                    ->after('login_text_color');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('merchants', 'members_page_text_color')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->dropColumn('members_page_text_color');
            });
        }

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('members_page_text_color');
        });
    }
};
