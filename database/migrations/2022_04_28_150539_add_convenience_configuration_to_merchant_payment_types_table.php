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
        Schema::table('merchant_payment_types', function (Blueprint $table) {
            $table->string('convenience_label')->default('Convenience Fee')->after('is_enabled');
            $table->double('convenience_fee', 10, 2)->nullable()->after('convenience_label');
            $table->foreignId('convenience_type_id')->nullable()->after('convenience_fee')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_payment_types', function (Blueprint $table) {
            $table->dropColumn([
                'convenience_label',
                'convenience_fee',
                'convenience_type_id'
            ]);
        });
    }
};
