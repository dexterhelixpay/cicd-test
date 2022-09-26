<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\PaymayaMid;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPaymayaMidCreation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paymaya-mid:fix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add Merchant ID to created mids in console';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        DB::transaction(function () {
            Merchant::query()
                ->whereNotNull('paymaya_vault_mid_console_id')
                ->orWhereNull('paymaya_pwp_mid_console_id')
                ->cursor()
                ->tapEach(function (Merchant $merchant) {
                    if ($vaultId = $merchant->paymaya_vault_mid_console_id) {
                        $mid = PaymayaMid::find($vaultId);

                        if ($mid) {
                            $mid->forceFill(['merchant_id' => $merchant->id])->update();
                        }
                    }

                    if ($pwpId = $merchant->paymaya_pwp_mid_console_id) {
                        $mid = PaymayaMid::find($pwpId);

                        if ($mid) {
                            $mid->forceFill(['merchant_id' => $merchant->id])->update();
                        }
                    }
                })->all();
        });
    }
}
