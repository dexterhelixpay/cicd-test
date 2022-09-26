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
        Schema::create('voucher_qualified_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_id')->index();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->json('mobile_numbers')->nullable();
            $table->json('emails')->nullable();
            $table->timestamps();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->boolean('is_secure_voucher')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_qualified_customers');

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['is_secure_voucher']);
        });
    }
};
