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
        Schema::table('products', function (Blueprint $table) {
            $table->string('discord_channel')->nullable();
            $table->unsignedBigInteger('discord_role_id')->nullable();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('discord_user_id')->nullable();
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->integer('discord_days_unpaid_limit')->nullable();
        });

        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->boolean('is_active_discord_member')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'discord_channel',
                'discord_role_id'
            ]);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('discord_user_id');
        });


        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('discord_days_unpaid_limit');
        });

        Schema::table('subscribed_products', function (Blueprint $table) {
            $table->dropColumn('is_active_discord_member');
        });
    }
};
