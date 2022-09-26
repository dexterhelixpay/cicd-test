<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCheckoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->index();

            $table->json('subscription');
            $table->json('products');
            $table->json('customer')->nullable();
            $table->unsignedInteger('max_payment_count')->nullable();

            $table->string('checkout_url')->nullable();
            $table->string('success_redirect_url');
            $table->string('failure_redirect_url');

            $table->timestamp('expires_at');
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
        Schema::dropIfExists('checkouts');
    }
}
