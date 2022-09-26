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
            $table->boolean('is_telegram_enabled')->default(false);
            $table->text('telegram_header_text')->nullable();
            $table->text('telegram_subheader_text')->nullable();
            $table->text('telegram_invite_button_text')->nullable();
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
                'is_telegram_enabled',
                'telegram_header_text',
                'telegram_subheader_text',
                'telegram_invite_button_text'
            ]);
        });
    }
};
