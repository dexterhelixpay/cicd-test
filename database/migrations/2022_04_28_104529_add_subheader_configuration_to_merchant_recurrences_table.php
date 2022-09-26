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
        Schema::table('merchant_recurrences', function (Blueprint $table) {
            $table->boolean('is_discount_label_enabled')->default(true);
            $table->string('subheader')->nullable();
        });

        Schema::table('product_recurrences', function (Blueprint $table) {
            $table->boolean('is_discount_label_enabled')->default(true);
            $table->string('subheader')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_recurrences', function (Blueprint $table) {
            $table->dropColumn(['is_discount_label_enabled', 'subheader']);
        });

        Schema::table('product_recurrences', function (Blueprint $table) {
            $table->dropColumn(['is_discount_label_enabled', 'subheader']);
        });
    }
};
