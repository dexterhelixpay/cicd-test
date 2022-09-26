<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmailVerifiedAtToMerchantUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchant_users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('verification_code')->nullable()->after('is_enabled');
            $table->timestamp('email_verified_at')->nullable()->after('verification_code');
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
            $table->string('password')->nullable(false)->change();
            $table->dropColumn('verification_code', 'email_verified_at');
        });
    }
}
