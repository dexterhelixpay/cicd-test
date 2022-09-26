<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPasswordUpdatedAtToUserTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_updated_at')->nullable()->after('email_verified_at');
        });

        Schema::table('merchant_users', function (Blueprint $table) {
            $table->timestamp('password_updated_at')->nullable()->after('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_updated_at');
        });

        Schema::table('merchant_users', function (Blueprint $table) {
            $table->dropColumn('password_updated_at');
        });
    }
}
