<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantPaymentTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchant_payment_types', function (Blueprint $table) {
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('payment_type_id');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_globally_enabled')->default(false);
            $table->json('payment_methods')->nullable();
            $table->unsignedInteger('sort_number')->default(1);
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
        Schema::dropIfExists('merchant_payment_methods');
    }
}
