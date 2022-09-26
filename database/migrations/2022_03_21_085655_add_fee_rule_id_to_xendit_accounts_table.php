<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFeeRuleIdToXenditAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('xendit_accounts', function (Blueprint $table) {
            $table->string('xendit_fee_rule_id')->nullable()->after('xendit_account_id');
            $table->string('fee_unit')->nullable()->after('status');
            $table->double('fee_amount', 10, 2)->nullable()->after('fee_unit');
            $table->double('overall_paid_transactions_threshold', 10, 2)->nullable()->after('fee_amount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('xendit_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'xendit_fee_rule_id',
                'fee_unit',
                'fee_amount',
                'overall_paid_transactions_threshold',
            ]);
        });
    }
}
