<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVouchersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');

            $table->string('code');
            $table->unsignedInteger('type')->comment('1 = Fixed, 2 = Percentage');

            $table->double('amount', 10, 2);
            $table->unsignedBigInteger('total_count');
            $table->unsignedBigInteger('remaining_count');
            $table->double('minimum_purchase_amount', 10, 2)->default(0);

            $table->boolean('is_enabled')->default(true);

            $table->timestamp('expires_at')->nullable();

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
        Schema::dropIfExists('vouchers');
    }
}
