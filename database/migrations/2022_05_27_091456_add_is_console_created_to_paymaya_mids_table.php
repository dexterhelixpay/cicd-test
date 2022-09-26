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
        Schema::table('paymaya_mids', function (Blueprint $table) {
            $table->boolean('is_console_created')->after('is_pwp')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('paymaya_mids', function (Blueprint $table) {
            $table->dropColumn('is_console_created');
        });
    }
};
