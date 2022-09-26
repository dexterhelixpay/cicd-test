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

        if (!Schema::hasColumns('product_recurrences', [
            'name',
            'initially_discounted_order_count',
            'initially_discounted_price',
            'initial_discount_percentage',
            'initial_discount_label',
        ])) {

            Schema::table('product_recurrences', function (Blueprint $table) {
                $table->string('name')->nullable()->after('code');
                $table->integer('initially_discounted_order_count')->nullable();
                $table->double('initially_discounted_price', 10, 2)->nullable();
                $table->double('initial_discount_percentage', 10, 2)->nullable();
                $table->string('initial_discount_label')->nullable();
            });
        }

        if (!Schema::hasColumn('product_recurrences','is_discount_label_enabled')) {
            Schema::table('product_recurrences', function (Blueprint $table) {
                $table->boolean('is_discount_label_enabled')->default(true);
            });
        }

        if (Schema::hasColumn('products', 'recurrence_pricing')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('recurrence_pricing');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_recurrences', function (Blueprint $table) {
            $table->dropColumns([
                'name',
                'initially_discounted_order_count',
                'initially_discounted_price',
                'initial_discount_percentage',
                'initial_discount_label',
                'is_discount_label_enabled',
            ]);
        });
    }
};
