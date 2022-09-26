<?php

use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('payment_type_id');
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('payment_status_id')->default(PaymentStatus::NOT_INITIALIZED);
            $table->unsignedBigInteger('order_status_id')->default(OrderStatus::UNPAID);
            $table->unsignedBigInteger('shipping_method_id')->nullable();

            $table->double('shipping_fee')->nullable();
            $table->double('bank_fee', 10, 2)->nullable();
            $table->double('original_price', 10, 2)->nullable();
            $table->double('total_price', 10, 2)->nullable();
            $table->json('payment_info')->nullable();
            $table->text('payment_url')->nullable();
            $table->unsignedBigInteger('payment_attempts')->default(0);

            $table->string('payor');
            $table->date('billing_date');
            $table->string('billing_address');
            $table->string('billing_province');
            $table->string('billing_city');
            $table->string('billing_barangay');

            $table->string('recipient')->nullable();
            $table->date('shipping_date')->nullable();
            $table->string('shipping_address')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_province')->nullable();
            $table->string('shipping_barangay')->nullable();

            $table->string('order_number')->nullable();

            $table->string('paymaya_card_token_id')->nullable();
            $table->string('paymaya_card_type')->nullable();
            $table->string('paymaya_masked_pan')->nullable();

            $table->string('brankas_masked_pan')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('payment_attempted_at')->nullable();

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
        Schema::dropIfExists('orders');
    }
}
