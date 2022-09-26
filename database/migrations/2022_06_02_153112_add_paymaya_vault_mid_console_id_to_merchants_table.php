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
            $table->unsignedBigInteger('paymaya_vault_mid_console_id')->nullable()->index()
                ->after('paymaya_vault_mid_id');
            $table->unsignedBigInteger('paymaya_pwp_mid_console_id')->nullable()->index()
                ->after('paymaya_pwp_mid_id');


            $table->text('paymaya_pwp_console_public_key')->nullable()->after('paymaya_pwp_public_key');
            $table->text('paymaya_pwp_console_secret_key')->nullable()->after('paymaya_pwp_secret_key');

            $table->text('paymaya_vault_console_public_key')->nullable()->after('paymaya_vault_public_key');
            $table->text('paymaya_vault_console_secret_key')->nullable()->after('paymaya_vault_secret_key');
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
                'paymaya_vault_mid_console_id',
                'paymaya_pwp_mid_console_id',
                'paymaya_pwp_console_public_key',
                'paymaya_pwp_console_secret_key',
                'paymaya_vault_console_public_key',
                'paymaya_vault_console_secret_key'
            ]);
        });
    }
};
