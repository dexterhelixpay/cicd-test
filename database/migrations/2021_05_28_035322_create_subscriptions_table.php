<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->index();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('payment_type_id');
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('shipping_method_id')->nullable();

            $table->string('payor');
            $table->string('billing_address');
            $table->string('billing_province');
            $table->string('billing_city');
            $table->string('billing_barangay');

            $table->string('recipient')->nullable();
            $table->string('shipping_address')->nullable();
            $table->string('shipping_province')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_barangay')->nullable();

            $table->json('payment_schedule');
            $table->unsignedInteger('max_payment_count')->nullable();
            $table->date('last_billing_date')->nullable();
            $table->double('shipping_fee', 10, 2)->nullable();
            $table->double('bank_fee', 10, 2)->nullable();
            $table->double('original_price', 10, 2)->nullable();
            $table->double('total_price', 10, 2)->nullable();
            $table->double('total_amount_paid', 10, 2)->default(0);

            $table->string('reference_id')->nullable();
            $table->string('success_redirect_url')->nullable();
            $table->string('failure_redirect_url')->nullable();

            $table->string('paymaya_payment_token_id')->nullable();
            $table->string('paymaya_verification_url')->nullable();
            $table->string('paymaya_card_token_id')->nullable();
            $table->string('paymaya_card_type')->nullable();
            $table->string('paymaya_masked_pan')->nullable();

            $table->string('brankas_masked_pan')->nullable();

            $table->timestamp('cancelled_at')->nullable();
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
        Schema::dropIfExists('subscriptions');
    }
}
