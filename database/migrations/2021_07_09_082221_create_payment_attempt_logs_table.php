<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentAttemptLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_attempt_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('transaction_id')->nullable();
            $table->unsignedBigInteger('payment_type_id');
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('payment_status_id');

            $table->double('total_price', 10, 2)->nullable();
            $table->json('payment_info')->nullable();
            $table->text('payment_url')->nullable();

            $table->string('paymaya_card_token_id')->nullable();
            $table->string('paymaya_card_type')->nullable();
            $table->string('paymaya_masked_pan')->nullable();

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
        Schema::dropIfExists('payment_attempt_logs');
    }
}
