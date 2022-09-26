<?php

namespace App\Console\Commands;

use App\Models\PaymayaMerchant;
use Illuminate\Console\Command;

class PaymayaMerchantAdd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paymaya-merchant:add
        {id : The merchant ID}
        {label : The label}
        {--public= : The public key}
        {--secret= : The secret key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a new PayMaya merchant';

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
        $merchant = PaymayaMerchant::make()
            ->forceFill([
                'label' => $this->argument('label'),
                'merchant_id' => $this->argument('id'),
            ]);

        $merchant->save();

        $merchant->keys()->make()->forceFill([
            'key' => $this->option('public') ?? config('services.paymaya.vault.public_key'),
            'is_secret' => false,
        ])->save();

        $merchant->keys()->make()->forceFill([
            'key' => $this->option('secret') ?? config('services.paymaya.vault.secret_key'),
            'is_secret' => true,
        ])->save();

        return 0;
    }
}
