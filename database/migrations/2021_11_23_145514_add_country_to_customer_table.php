<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCountryToCustomerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('customers', 'country_name')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('country_name')->nullable()->after('address');
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
        if (Schema::hasColumn('customers', 'country')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('country_name');
            });
        }
    }
}
