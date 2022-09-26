<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->index();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('mobile_number')->nullable();

            $table->string('address');
            $table->string('province');
            $table->string('city');
            $table->string('barangay');

            $table->string('verification_code')->nullable();

            $table->string('paymaya_uuid')->nullable();

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
        Schema::dropIfExists('customers');
    }
}
