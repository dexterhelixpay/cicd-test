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
        Schema::create('subscription_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->index();
            $table->unsignedBigInteger('subscription_count')->default(0);
            $table->unsignedBigInteger('links_opened_count')->default(0);
            $table->double('total_amount', 10, 2)->nullable();
            $table->double('purchased_amount', 10, 2)->nullable();
            $table->double('ltv_amount', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_import_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscription_imports');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('subscription_import_id');
        });
    }
};
