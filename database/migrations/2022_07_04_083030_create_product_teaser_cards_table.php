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
        Schema::create('product_teaser_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->text('headline');
            $table->text('subheader');
            $table->text('button_text');
            $table->text('button_color');
            $table->string('video_link')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('sort_number')->default(1);
            $table->boolean('is_visible')->default(false);
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
        Schema::dropIfExists('product_teaser_cards');
    }
};
