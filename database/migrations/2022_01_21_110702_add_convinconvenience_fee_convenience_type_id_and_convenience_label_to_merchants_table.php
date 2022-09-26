<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConvinconvenienceFeeConvenienceTypeIdAndConvenienceLabelToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('convenience_label')->default('Convenience Fee')->after('card_discount');
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
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('convenience_label');
            $table->dropColumn('convenience_fee');
            $table->dropColumn('convenience_type_id');
        });
    }
}
