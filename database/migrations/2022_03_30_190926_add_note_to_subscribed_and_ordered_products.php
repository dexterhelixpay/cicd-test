<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNoteToSubscribedAndOrderedProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->json('sku_meta_notes')->nullable();
        });
        Schema::table('ordered_products', function (Blueprint $table) {
            $table->json('sku_meta_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->dropColumn('sku_meta_notes');
        });
        Schema::table('ordered_products', function (Blueprint $table) {
            $table->dropColumn('sku_meta_notes');
        });
    }
}
