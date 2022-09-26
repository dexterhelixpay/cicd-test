<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\PricingType;
use App\Notifications\MerchantSetProductPriceNotification;
use Illuminate\Console\Command;

class MerchantReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant:remind
        {--days-before= : The number of days before the billing date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remind the variable type merchants to set the amount of the order before the billing date.';

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
        $billingDate = now()->addDays($this->option('days-before'))->toDateString();

        Merchant::query()
            ->whereNotNull('verified_at')
            ->where('pricing_type_id',  PricingType::VARIABLE_PRICING)
            ->whereHas('subscriptions', function ($query) {
                $query->whereNull('completed_at')->whereNull('cancelled_at');
            })
            ->whereHas('orders', function ($query) use ($billingDate) {
                $query->whereDate('billing_date', $billingDate);
            })
            ->cursor()
            ->tapEach(function ($merchant) use ($billingDate) {
                $merchant->notify(
                    new MerchantSetProductPriceNotification(
                        $merchant,
                        $billingDate
                    )
                );
            })
            ->all();

        return 0;
    }
}
