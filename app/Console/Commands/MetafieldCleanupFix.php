<?php

namespace App\Console\Commands;

use App\Jobs\InstallShopifyMetafields;
use App\Libraries\Shopify\Shopify;
use App\Models\Merchant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MetafieldCleanupFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metafield-cleanup:fix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix display of metafield cleanup button';

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
        DB::transaction(function () {
            Merchant::query()
                ->whereNotNull('shopify_info')
                ->cursor()
                ->tapEach(function (Merchant $merchant) {
                    dispatch(new InstallShopifyMetafields($merchant))->afterResponse();
                })->all();
        });
    }
}
