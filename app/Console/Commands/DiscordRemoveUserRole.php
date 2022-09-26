<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Facades\Discord;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\OrderedProduct;
use Illuminate\Console\Command;
use App\Models\SubscribedProduct;
use Illuminate\Support\Facades\DB;

class DiscordRemoveUserRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:remove-user-role
        {--subscriptionIds=* : Remove only the users roles with the given subscription IDs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Remove the discord user's role with unpaid order";

    /**
     * Constant representing a default days the order was unpaid.
     *
     * @var int
     */
    const DAYS_UNPAID_LIMIT = 5;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Merchant::query()
            ->with([
                'subscriptions',
                'subscriptions.nextUnpaidOrder',
            ])
            ->whereNotNull('discord_guild_id')
            ->whereNull('deleted_at')
            ->cursor()
            ->tapEach(function (Merchant $merchant) {
                DB::transaction(function () use ($merchant) {
                    $subscriptions =$merchant->subscriptions
                        ->whereNotNull('discord_user_id')
                        ->when($this->option('subscriptionIds'), function($query){
                            return $query->whereIn('id',$this->option('subscriptionIds'));
                        })
                        ->all();

                    collect($subscriptions)->each(function(Subscription $subscription) use ($merchant){
                            if (!$order = $subscription->nextUnpaidOrder) return;

                            if (
                                $order &&
                                $order->billing_date->diffInDays(now(), false) >= ($merchant->discord_days_unpaid_limit ?: self::DAYS_UNPAID_LIMIT)
                            ) {
                                $order->products
                                    ->each(function(OrderedProduct $orderedProduct) {
                                        return $orderedProduct->subscribedProduct()->update([
                                            'is_active_discord_member' => false
                                        ]);
                                    })
                                    ->pluck('product')
                                    ->each(function(Product $product) use ($subscription, $merchant) {
                                            Discord::guilds()
                                            ->removeUserRole(
                                                $merchant->discord_guild_id,
                                                $subscription->discord_user_id,
                                                $product->discord_role_id
                                            );
                                    });
                            }
                        });
                });
            })
            ->all();

        return 0;
    }
}
