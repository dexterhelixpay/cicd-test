<?php

namespace App\Console\Commands\Customer;

use App\Facades\v2\PayMaya;
use App\Models\Customer;
use App\Models\CustomerCard;
use Illuminate\Console\Command;

class CustomerCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:cleanup
        {id* : The IDs of the customers to cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up incorrect customer data';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $customers = Customer::query()
            ->with('cards', 'merchant')
            ->whereKey($this->argument('id'))
            ->get();

        if ($customers->isEmpty()) {
            return $this->getOutput()->error('No customers found');
        }

        $deletedCardCount = 0;

        $customers->each(function (Customer $customer) use (&$deletedCardCount) {
            $customer->cards->each(function (CustomerCard $card) use ($customer, &$deletedCardCount) {
                $response = PayMaya::customerCards()->find(
                    $customer->paymaya_uuid,
                    $card->card_token_id,
                    $customer->merchant->paymaya_vault_console_public_key
                        ?? $customer->merchant->paymaya_vault_secret_key
                );

                if ($response->failed()) {
                    $card->delete();

                    $deletedCardCount++;
                }
            });
        });

        $this->getOutput()->success(
            "{$deletedCardCount} card(s) deleted from {$customers->count()} customer(s)"
        );

        return Command::SUCCESS;
    }
}
