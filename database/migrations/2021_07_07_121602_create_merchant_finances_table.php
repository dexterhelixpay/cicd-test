<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantFinancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchant_finances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');

            $table->date('remittance_date');

            $table->integer('no_of_payments');
            $table->double('total_value', 10, 2);

            $table->string('google_link')->nullable();

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
        Schema::dropIfExists('merchant_finances');
    }
}
