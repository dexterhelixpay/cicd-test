<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pricing_type_id')->nullable()->index();

            $table->string('username');
            $table->string('email')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('password');

            $table->string('name');
            $table->string('subdomain')->nullable()->index();
            $table->string('description_title')->nullable();
            $table->json('description_items')->nullable();

            $table->string('logo_image_path')->nullable();
            $table->string('logo_svg_path')->nullable();
            $table->string('background_color')->nullable();
            $table->string('header_background_color')->nullable();
            $table->string('highlight_color')->nullable();
            $table->string('on_background_color')->default('#2F2F2F');

            $table->string('website_url')->nullable();
            $table->string('instagram_handle')->nullable();

            $table->double('card_discount', 5, 2)->nullable();

            $table->boolean('is_enabled')->default(true);
            $table->boolean('has_api_access')->default(false);
            $table->boolean('has_shippable_products')->default(true);

            $table->boolean('are_orders_skippable')->default(true);
            $table->boolean('are_orders_cancellable')->default(true);
            $table->boolean('are_multiple_products_selectable')->default(true);

            $table->longText('faqs')->nullable();
            $table->string('faqs_title')->nullable();
            $table->boolean('is_faqs_enabled')->default(true);

            $table->timestamp('verified_at')->nullable();
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
        Schema::dropIfExists('merchants');
    }
}
