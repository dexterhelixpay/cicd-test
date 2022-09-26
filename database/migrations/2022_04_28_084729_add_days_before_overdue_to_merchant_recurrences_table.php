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
        Schema::table('merchant_recurrences', function (Blueprint $table) {
            $table->unsignedInteger('days_before_overdue')->nullable()->after('recurrence_month');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_recurrences', function (Blueprint $table) {
            $table->dropColumn('days_before_overdue');
        });
    }
};
