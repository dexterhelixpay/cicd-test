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
        Schema::create('home_page_cards', function (Blueprint $table) {
            $table->id();
            $table->string('image_path')->nullable();
            $table->string('card_link');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_number')->default(1);
            $table->json('restricted_merchant_ids')->nullable();
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
        Schema::dropIfExists('home_page_cards');
    }
};
