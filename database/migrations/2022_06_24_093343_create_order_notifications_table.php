<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->nullable()->index();

            $table->string('purchase_type');
            $table->string('notification_type');
            $table->string('subscription_type')->nullable();
            $table->string('applicable_orders')->nullable();
            $table->integer('days_from_billing_date')->nullable();
            $table->json('recurrences')->nullable();

            $table->string('subject');
            $table->string('headline')->nullable();
            $table->string('subheader')->nullable();

            $table->string('payment_headline')->nullable();
            $table->string('payment_instructions')->nullable();
            $table->string('payment_button_label')->nullable();

            $table->string('total_amount_label');

            $table->string('payment_instructions_headline')->nullable();
            $table->string('payment_instructions_subheader')->nullable();

            $table->boolean('is_payment_successful')->nullable();
            $table->boolean('has_payment_lapsed')->nullable();
            $table->boolean('is_enabled')->default(true);

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
        Schema::dropIfExists('order_notifications');
    }
};
