<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use Illuminate\Console\Command;

class MerchantLimitReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant-limit:reset
        {--isFlagIncluded=false : The maximum limit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset hourly total amount of merchants';

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
        Merchant::query()
            ->where('is_enabled', true)
            ->where('has_reached_max_amount', true)
            ->whereNotNull('verified_at')
            ->cursor()
            ->tapEach(function ($merchant) {
                $merchant->hourly_total_amount_paid = null;

                if ($this->option('isFlagIncluded')) {
                    $merchant->has_reached_max_amount = false;
                }

                $merchant->update();
            })
            ->all();
    }
}
