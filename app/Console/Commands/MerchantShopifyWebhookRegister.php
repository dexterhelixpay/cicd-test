<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Libraries\Shopify\Rest\Webhook as ShopifyWebhook;
use Illuminate\Console\Command;

class MerchantShopifyWebhookRegister extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant:register-shopify-webhook
        {--topics= : The shopify topic}
        {--merchantIds= : The merchant ids that will subscribe to the topic}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register merchant shopify webhook based on the given option.';

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
        if ($this->option('merchantIds')) {
            collect(explode(',', $this->option('merchantIds')))
                ->filter(function($merchantId) {
                    $merchant = Merchant::whereKey($merchantId)->first();

                    return $merchant && $merchant->shopify_info;
                })->each(function ($merchantId) {
                    $merchant = Merchant::find($merchantId);

                    if (!$merchant) return;

                    collect(explode(',', $this->option('topics')))
                        ->each(function ($topic) use($merchant) {
                            $this->registerWebhook($merchant, $topic);
                        });
                });
        } else {
            Merchant::query()
                ->whereNotNull('shopify_info')
                ->cursor()
                ->tapEach(function (Merchant $merchant) {
                    collect(explode(',', $this->option('topics')))
                    ->each(function ($topic) use($merchant) {
                        $this->registerWebhook($merchant, $topic);
                    });
                })->all();
        }
    }

     /**
     * Register Webhook
     * @param  \App\Models\Merchant  $merchant
     * @param string $topic
     *
     * @return void
     */
    protected function registerWebhook($merchant, $topic)
    {
        (new ShopifyWebhook($merchant->shopify_domain, $merchant->shopify_info['access_token']))
        ->create([
            'topic' => $topic,
            'address' => env('APP_URL').'/v1/shopify/webhooks',
            'format' => 'json',
            'metafield_namespaces' => [
                'bukopay'
            ]
        ])->then(function () {
        })
        ->wait(false);
    }
}
