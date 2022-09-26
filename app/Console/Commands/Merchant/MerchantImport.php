<?php

namespace App\Console\Commands\Merchant;

use App\Facades\Xendit;
use App\Imports\XenditAccounts;
use App\Models\Merchant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class MerchantImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant:import
        {--xendit : Import Xendit accounts}
        {--path= : The path to the import file}
        {--disk= : The filesystem instance to get the file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import merchants';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->option('xendit')) {
            return $this->importXenditAccounts();
        }

        return Command::SUCCESS;
    }

    /**
     * Import Xendit accounts.
     *
     * @return void
     */
    protected function importXenditAccounts()
    {
        if (!$this->option('path')) {
            return $this->getOutput()->error('The path option is required.');
        }

        $workbook = (new XenditAccounts)
            ->toCollection($this->option('path'), $this->option('disk'));

        $accounts = $workbook
            ->filter(function (Collection $rows) {
                return $rows->contains(function (Collection $row) {
                    return $row->has('bid');
                });
            })
            ->first();

        $bar = $this->output->createProgressBar($accounts->count());

        $accounts = $accounts
            ->map(function ($account, $row) use ($bar) {
                if (!$account->get('bid') || !$account->get('merchant_id')) {
                    $bar->advance();

                    return [
                        $row + 2,
                        'MISSING BID OR MERCHANT ID',
                        collect([$account->get('merchant_id'), $account->get('brand_name')])->filter()->join(' - '),
                        $account->get('bid') ?? 'N/A',
                        'N/A',
                        'N/A',
                    ];
                }

                if (!$merchant = Merchant::find($account->get('merchant_id'))) {
                    $bar->advance();

                    return [
                        $row + 2,
                        'INVALID MERCHANT ID',
                        'N/A',
                        $account->get('bid') ?? 'N/A',
                        'N/A',
                        'N/A',
                    ];
                }

                $response = Xendit::accounts()->find($account->get('bid'));

                if ($response->failed()) {
                    $bar->advance();

                    return [
                        $row + 2,
                        $response->reason(),
                        join(' - ', [$merchant->getKey(), $account->get('brand_name')]),
                        // collect([$account->get('merchant_id'), $account->get('brand_name')])->filter()->join(' - '),
                        $account->get('bid') ?? 'N/A',
                        'N/A',
                        'N/A',
                    ];
                }

                $xenditAccount = $merchant->xenditAccount()->firstOrNew()
                    ->forceFill([
                        'xendit_account_id' => $account->get('bid'),
                        'email' => $response->json('email'),
                        'status' => $response->json('status'),
                    ]);

                $xenditAccount->save();

                return [
                    $row + 2,
                    'CREATED',
                    join(' - ', [$merchant->getKey(), $account->get('brand_name')]),
                    $xenditAccount->xendit_account_id,
                    $xenditAccount->email,
                    $xenditAccount->status,
                    // collect([$account->get('merchant_id'), $account->get('brand_name')])->filter()->join(' - '),
                    // $response->json('id'),
                    // $response->json('email'),
                    // $response->json('status'),
                ];
            });

        $bar->finish();

        $this->line("\n");
        $this->table([
            'Row', 'Status', 'Merchant', 'Xendit Account ID', 'Email', 'Account Status'],
            $accounts->toArray()
        );
    }
}
