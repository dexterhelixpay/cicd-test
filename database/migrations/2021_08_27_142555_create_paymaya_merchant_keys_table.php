<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymayaMerchantKeysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('paymaya_merchant_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('paymaya_merchant_id');
            $table->string('key');
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('paymaya_merchant_keys');
    }
}
