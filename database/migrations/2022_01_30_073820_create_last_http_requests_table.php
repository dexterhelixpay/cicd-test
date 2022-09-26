<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLastHttpRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('last_http_requests', function (Blueprint $table) {
            $table->id();
            $table->morphs('user');
            $table->longText('token')->nullable();
            $table->string('device')->nullable();
            $table->string('operating_system')->nullable();
            $table->string('browser')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('request_uri')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('last_http_requests');
    }
}
