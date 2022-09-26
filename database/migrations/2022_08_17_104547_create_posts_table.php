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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->nullable()->index();
            $table->unsignedBigInteger('blast_id')->nullable()->index();

            $table->string('type')->index();
            $table->string('video_type')->nullable();

            $table->string('headline');
            $table->text('subheadline')->nullable();
            $table->text('description')->nullable();
            $table->longText('body')->nullable();

            $table->string('banner_image_path')->nullable();
            $table->string('banner_link')->nullable();

            $table->string('video_id')->nullable();
            $table->string('video_url')->nullable();
            $table->string('video_embed_url')->nullable();
            $table->text('video_embed_html')->nullable();
            $table->json('video_info')->nullable();

            $table->boolean('is_visible')->default(true);

            $table->timestamp('published_at')->nullable()->index();
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
        Schema::dropIfExists('posts');
    }
};
