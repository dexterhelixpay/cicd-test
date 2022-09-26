<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use Illuminate\Console\Command;
use App\Models\SubscriptionImport;
use Illuminate\Support\Facades\DB;

class SubscriptionImportUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription-import:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the opened links count on the imported subscriptions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Merchant::query()
            ->with(['subscriptionImports.subscriptions'])
            ->has('subscriptionImports')
            ->whereNull('deleted_at')
            ->cursor()
            ->tapEach(function (Merchant $merchant) {
                DB::transaction(function () use ($merchant) {
                    $merchant->subscriptionImports()
                        ->where(function($query){
                            $query->whereNull('open_percentage')
                                ->orWhere('open_percentage', '<', 100);
                        })
                        ->each(function(SubscriptionImport $import){
                            $scheduleEmail = $import->scheduleEmail;
                            if ($scheduleEmail?->email?->unique_open_count > 0) {
                                $linksOpenedCount = (int) $scheduleEmail->email?->unique_open_count;
                                $import->update([
                                    'links_opened_count' => $linksOpenedCount,
                                    'open_percentage' => ($linksOpenedCount/ $import->subscription_count) * 100
                                ]);
                            }
                        });
                });
            })
            ->all();

        return 0;
    }
}
