<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantEmailBlastsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchant_email_blasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');

            $table->string('subject');
            $table->string('title');
            $table->text('subtitle');
            
            $table->string('banner_image_path')->nullable();
            $table->string('banner_url')->nullable();

            $table->json('targeted_customer_ids')->nullable();
            $table->unsignedBigInteger('targeted_customer_count')->nullable();

            $table->softDeletes();
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
        Schema::dropIfExists('merchant_email_blasts');
    }
}
