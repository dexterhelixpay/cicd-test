<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantDescriptionItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchant_description_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->index();

            $table->text('description');
            $table->string('icon_path')->nullable();
            $table->string('emoji')->nullable();
            $table->unsignedInteger('sort_number');

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
        Schema::dropIfExists('merchant_description_items');
    }
}
