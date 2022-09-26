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
        Schema::create('custom_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->index();
            $table->string('title');
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_customer_details')->default(false);
            $table->boolean('is_address_details')->default(false);
            $table->unsignedInteger('sort_number')->default(1);
            $table->timestamps();
        });

        Schema::table('subscription_custom_fields', function (Blueprint $table) {
            $table->unsignedBigInteger('custom_component_id')->after('merchant_id')->index();
            $table->boolean('is_default')->default(false)->after('is_visible');
            $table->unsignedInteger('sort_number')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('custom_components');

        Schema::table('subscription_custom_fields', function (Blueprint $table) {
            $table->dropColumn(['custom_component_id', 'sort_number', 'is_default']);
        });
    }
};
