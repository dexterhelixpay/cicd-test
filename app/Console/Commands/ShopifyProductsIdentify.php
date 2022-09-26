<?php

namespace App\Console\Commands;

use App\Libraries\Shopify\Shopify;
use App\Models\Merchant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ShopifyProductsIdentify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify-products:identify
        {--type=0 : 0 if unimported, 1 for imported }
        {--merchantId= : The merchant id }
        {--to= : The email recipients }
        {--cc= : The cc recipients }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identify shopify products if its imported/unimported and send the list via email.';

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
        if (!$this->option('merchantId')) return;

        $merchant = Merchant::find($this->option('merchantId'));

        if (!Cache::has("merchants:{$merchant->id}:shopify_products:identifying")) {
            Cache::put("merchants:{$merchant->id}:shopify_products:identifying", 1, 3600);
            Cache::put(
                "merchants:{$merchant->id}:shopify_products:identifying:type",
                $this->option('type'),
                3600
            );

            if ($this->option('cc')) {
                Cache::put(
                    "merchants:{$merchant->id}:shopify_products:identifying:cc",
                    explode(',', $this->option('cc')),
                    3600
                );
            }

            Cache::put(
                "merchants:{$merchant->id}:shopify_products:identifying:to",
                $this->option('to'),
                3600
            );

            $shopify = new Shopify(
                $merchant->shopify_domain, $merchant->shopify_info['access_token']
            );

            $shopify->createBulkOperationsWebhook();
            $shopify->getAllProducts();
        }
    }
}
