<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantRecurrencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchant_recurrences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');

            $table->string('name');
            $table->string('code');
            $table->string('description')->nullable();

            $table->unsignedBigInteger('recurrence_day')->nullable();

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
        Schema::dropIfExists('merchant_recurrences');
    }
}
