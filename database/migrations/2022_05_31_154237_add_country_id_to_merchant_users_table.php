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
        Schema::table('merchant_users', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('email');
            $table->string('formatted_mobile_number')->nullable()->after('mobile_number');
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('email');
            $table->text('formatted_mobile_number')->nullable()->after('mobile_number');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('formatted_mobile_number')->nullable()->after('mobile_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_users', function (Blueprint $table) {
            $table->dropColumn(['country_id', 'formatted_mobile_number']);
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['country_id', 'formatted_mobile_number']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['formatted_mobile_number']);
        });
    }
};
