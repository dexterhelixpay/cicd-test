<?php

namespace App\Console\Commands;

use App\Facades\PayMaya;
use App\Models\PaymayaMid;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PaymayaWebhookSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paymaya:webhook-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync webhooks to all PayMaya merchants';

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
        $events = ['PAYMENT_EXPIRED', 'PAYMENT_FAILED', 'PAYMENT_SUCCESS'];

        PaymayaMid::query()
            ->whereNotNull('secret_key')
            ->cursor()
            ->tapEach(function (PaymayaMid $mid) use ($events) {
                PayMaya::withVaultKeys(
                    $mid->getRawOriginal('public_key'),
                    $mid->getRawOriginal('secret_key'),
                    function () use ($events) {
                        Artisan::call('webhook:create', [
                            'gateway' => 'paymaya',
                            '--url' => route('api.v1.payments.paymaya.events'),
                            '--events' => $events,
                        ], $this->getOutput()->getOutput());
                });
            })
            ->all();

        return Command::SUCCESS;
    }
}
