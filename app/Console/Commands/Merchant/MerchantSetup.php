<?php

namespace App\Console\Commands\Merchant;

use App\Facades\Xendit;
use App\Models\Merchant;
use Illuminate\Console\Command;

class MerchantSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant:setup
        {id : The merchant ID}
        {--xendit-account= : The Xendit account ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup accounts for a merchant';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!$merchant = Merchant::find($this->argument('id'))) {
            return $this->getOutput()->error('Merchant not found');
        }

        $question = "The selected merchant is {$merchant->name} ({$merchant->getKey()}). Continue?";

        if (!$this->confirm($question)) {
            return;
        }

        if ($xenditAccountId = $this->option('xendit-account')) {
            $response = Xendit::accounts()->find($xenditAccountId);

            if ($response->successful()) {
                $xenditAccount = $merchant->xenditAccount()->firstOrNew()
                    ->forceFill([
                        'xendit_account_id' => $response->json('id'),
                        'email' => $response->json('email'),
                        'status' => $response->json('status'),
                    ]);

                $xenditAccount->save();

                $this->getOutput()->success('Xendit account added');
            } else {
                $this->getOutput()->error($response->reason());
            }
        }

        return 0;
    }
}
