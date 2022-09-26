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
        Schema::table('ordered_products', function (Blueprint $table) {
            $table->json('product_properties')->nullable()->after('product_variant_id');
        });

        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->json('product_properties')->nullable()->after('product_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ordered_products', function (Blueprint $table) {
            $table->dropColumn('product_properties');
        });

        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->dropColumn('product_properties');
        });
    }
};
