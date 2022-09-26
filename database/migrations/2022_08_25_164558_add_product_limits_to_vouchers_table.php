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
        Schema::table('vouchers', function (Blueprint $table) {
            $table->json('product_limits')->nullable();
        });

        Schema::table('used_vouchers', function (Blueprint $table) {
            $table->json('product_limit_info')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('product_limits');
        });

        Schema::table('used_vouchers', function (Blueprint $table) {
            $table->dropColumn('product_limit_info');
        });
    }
};
