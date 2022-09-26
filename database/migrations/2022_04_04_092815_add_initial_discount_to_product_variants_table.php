<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInitialDiscountToProductVariantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('initially_discounted_order_count')->nullable()->after('sku');
            $table->double('initially_discounted_price', 10, 2)->nullable()->after('initially_discounted_order_count');
            $table->double('initial_discount_percentage', 10, 2)->nullable()->after('initially_discounted_price');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'initially_discounted_order_count',
                'initially_discounted_price',
                'initial_discount_percentage',
            ]);
        });
    }
}
