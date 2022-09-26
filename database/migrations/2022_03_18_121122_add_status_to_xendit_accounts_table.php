<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToXenditAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('xendit_accounts', function (Blueprint $table) {
            $table->string('email')->after('xendit_account_id');
            $table->string('status')->after('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('xendit_accounts', function (Blueprint $table) {
            $table->dropColumn('email', 'status');
        });
    }
}
