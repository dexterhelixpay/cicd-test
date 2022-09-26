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
        Schema::table('merchant_email_blasts', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->text('subtitle')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_email_blasts', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
            $table->text('subtitle')->nullable(false)->change();
        });
    }
};
