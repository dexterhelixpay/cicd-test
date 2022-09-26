<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDropdownSelectionToSubscriptionCustomFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('subscription_custom_fields', 'dropdown_selection')) {
            Schema::table('subscription_custom_fields', function (Blueprint $table) {
                $table->json('dropdown_selection')->nullable()->after('data_type');
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
        if (!Schema::hasColumn('subscription_custom_fields', 'dropdown_selection')) {
            Schema::table('subscription_custom_fields', function (Blueprint $table) {
                $table->dropColumn('dropdown_selection');
            });
        }
    }
}
