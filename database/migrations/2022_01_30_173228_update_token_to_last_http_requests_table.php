<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTokenToLastHttpRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('last_http_requests', function (Blueprint $table) {
            $table->dropColumn(['device', 'operating_system']);
            $table->json('token')->nullable()->change();
            $table->boolean('is_revoke')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('last_http_requests', function (Blueprint $table) {
            $table->string('token');
            $table->string('device')->nullable();
            $table->string('operating_system')->nullable();
            $table->dropColumn('is_revoke');
        });
    }
}
