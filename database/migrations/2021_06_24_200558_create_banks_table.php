<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code');

            $table->string('image_path')->nullable();

            $table->double('min_value', 10, 2)->nullable();
            $table->double('max_value', 10, 2)->nullable();

            $table->double('daily_limit', 10, 2)->nullable();

            $table->double('fee', 10, 2)->nullable();

            $table->integer('no_of_free_transactions')->nullable();

            $table->string('payment_channel');

            $table->boolean('is_enabled')->default(false);

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
        Schema::dropIfExists('banks');
    }
}
