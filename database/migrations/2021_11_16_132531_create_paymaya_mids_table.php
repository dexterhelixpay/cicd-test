<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymayaMidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('paymaya_mids', function (Blueprint $table) {
            $table->id();
            $table->string('mid')->index();
            $table->string('business_segment');
            $table->double('mdr', 10 ,2);
            $table->string('mcc');
            $table->string('public_key')->nullable();
            $table->string('secret_key')->nullable();
            $table->boolean('is_vault')->default(false);
            $table->boolean('is_pwp')->default(false);
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
        Schema::dropIfExists('paymaya_mids');
    }
}
