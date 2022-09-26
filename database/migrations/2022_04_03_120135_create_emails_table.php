<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->integer('delivered_count')->nullable();
            $table->integer('processed_count')->nullable();
            $table->integer('open_count')->nullable();
            $table->double('open_rate', 10, 2)->nullable();
            $table->integer('click_count')->nullable();
            $table->double('click_rate', 10, 2)->nullable();

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
        Schema::dropIfExists('emails');
    }
}
