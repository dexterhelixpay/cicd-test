<?php

use App\Models\Merchant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
            $table->json('support_contact')->nullable()->after('support_url');
        });

        DB::transaction(function () {
            Merchant::query()
                ->whereNotNull('support_url')
                ->cursor()
                ->each(function (Merchant $merchant) {
                    $merchant->update([
                        'support_contact' => [
                            'type' => 'url',
                            'value' => $merchant->support_url,
                        ]
                    ]);
                })->all();
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
            $table->dropColumn('support_contact');
        });
    }
};
