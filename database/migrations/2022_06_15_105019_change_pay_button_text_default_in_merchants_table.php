<?php

use App\Models\Merchant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->string('pay_button_text')->nullable()->default('Pay Now')->change();
        });

        DB::transaction(function () {
            Merchant::query()
                ->where('pay_button_text', '=', 'Start Subscription')
                ->cursor()
                ->tapEach(function (Merchant $merchant)  {
                    $merchant->update([
                        'pay_button_text' => 'Pay Now'
                    ]);
                })
                ->all();
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
            $table->string('pay_button_text')->nullable()->default('Start Subscription')->change();
        });
    }
};
