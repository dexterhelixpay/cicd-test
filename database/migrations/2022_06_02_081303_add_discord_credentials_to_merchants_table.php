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
        Schema::table('merchants', function (Blueprint $table) {
            $table->text('discord_bot_token')->nullable();
            $table->text('discord_client_id')->nullable();
            $table->text('discord_client_secret')->nullable();
            $table->text('discord_guild_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn([
                'discord_bot_token',
                'discord_client_id',
                'discord_client_secret',
                'discord_guild_id'
            ]);
        });
    }
};
