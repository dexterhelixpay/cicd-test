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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_address')->nullable()->change();
            $table->string('billing_province')->nullable()->change();
            $table->string('billing_city')->nullable()->change();
            $table->string('billing_barangay')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('billing_address')->nullable()->change();
            $table->string('billing_province')->nullable()->change();
            $table->string('billing_city')->nullable()->change();
            $table->string('billing_barangay')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_address')->change();
            $table->string('billing_province')->change();
            $table->string('billing_city')->change();
            $table->string('billing_barangay')->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('billing_address')->change();
            $table->string('billing_province')->change();
            $table->string('billing_city')->change();
            $table->string('billing_barangay')->change();
        });
    }
};
