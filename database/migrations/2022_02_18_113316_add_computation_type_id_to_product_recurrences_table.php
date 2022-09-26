<?php

use App\Models\ProductRecurrence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddComputationTypeIdToProductRecurrencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_recurrences', function (Blueprint $table) {
            $table->unsignedInteger('computation_type_id')
                ->default(ProductRecurrence::SIMPLE)->after('discount_value');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_recurrences', function (Blueprint $table) {
            $table->dropColumn('computation_type_id');
        });
    }
}
