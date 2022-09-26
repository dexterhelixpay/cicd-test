<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DbCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up the database for the current environment';

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
        $env = app()->environment();

        if (!$this->confirm("This will clean up the database for the \"{$env}\" environment. Continue?")) {
            return;
        }

        switch ($env) {
            case 'development':
                return $this->cleanupDevelopment();

            case 'sandbox':
                return $this->cleanupSandbox();

            default:
                //
        }

        return 0;
    }

    /**
     * Clean up the development database.
     *
     * @return void
     */
    protected function cleanupDevelopment()
    {
        $bar = $this->getOutput()->createProgressBar();

        Merchant::query()
            ->whereKeyNot([115, 170, 172, 174])
            ->cursor()
            ->tapEach(function (Merchant $merchant) use (&$bar) {
                $this->deleteMerchant($merchant);

                $bar->advance();
            })
            ->all();

        $bar->finish();
    }

    /**
     * Clean up the sandbox database.
     *
     * @return void
     */
    protected function cleanupSandbox()
    {
        $bar = $this->getOutput()->createProgressBar();

        $this->updateAdminUsers();

        Merchant::query()
            ->whereKeyNot([
                188, // Peppa Pig Merchandise
                184, // Snacky
                182, // Dream Coffee
                179, // Helix Coffee Shop
                177, // Kapetearya
                176, // Kinsfolk Coffee
                172, // St. Roche
                115, // AllCare
            ])
            ->cursor()
            ->tapEach(function (Merchant $merchant) use (&$bar) {
                $this->deleteMerchant($merchant);

                $bar->advance();
            })
            ->all();

        $bar->finish();
    }

    /**
     * Update the users' emails to HelixPay.
     *
     * @return void
     */
    protected function updateAdminUsers()
    {
        DB::transaction(function () {
            User::query()
                ->withTrashed()
                ->get()
                ->each(function (User $user) {
                    if (
                        $user->trashed()
                        || Str::endsWith($user->email, '@gmail.com')
                    ) {
                        $user->roles()->detach();
                        $user->permissions()->detach();
                        $user->tokens()->delete();

                        return $user->forceDelete();
                    }

                    if (Str::endsWith($user->email, ['bukopay.ph', '@goodwork.ph'])) {
                        [$username] = explode('@', $user->email);

                        $hasHelixpayUser = User::query()
                            ->whereKeyNot($user->getKey())
                            ->where('email', "{$username}@helixpay.ph")
                            ->exists();

                        if ($hasHelixpayUser) {
                            $user->roles()->detach();
                            $user->permissions()->detach();
                            $user->tokens()->delete();

                            return $user->forceDelete();
                        }

                        return $user->updateQuietly([
                            'email' => "{$username}@helixpay.ph",
                        ]);
                    }
                });
        });
    }

    /**
     * Delete the given merchant.
     *
     * @return void
     */
    protected function deleteMerchant($merchant)
    {
        DB::transaction(function () use ($merchant) {
            $merchant->customers()->cursor()
                ->tapEach(function (Customer $customer) {
                    $customer->cards()->delete();
                    $customer->wallets()->delete();
                    $customer->forceDelete();
                })
                ->all();

            $merchant->products()->cursor()
                ->tapEach(function (Product $product) {
                    $product->allVariants()->get()->each(function (ProductVariant $variant) {
                        $variant->optionValues()->detach();
                        $variant->delete();
                    });

                    $product->options()->get()->each(function (ProductOption $option) {
                        $option->values()->delete();
                        $option->delete();
                    });

                    $product->images()->get()->each->delete();
                    $product->items()->delete();
                    $product->forceDelete();
                })
                ->all();

            $merchant->subscriptions()->cursor()
                ->tapEach(function (Subscription $subscription) {
                    $subscription->orders()->get()->each(function (Order $order) {
                        $order->attachments()->detach();
                        $order->products()->delete();
                        $order->attemptLogs()->delete();
                        $order->paymentInfoLogs()->delete();
                        $order->forceDelete();
                    });

                    $subscription->attachments()->get()->each->delete();
                    $subscription->products()->delete();
                    $subscription->forceDelete();
                })
                ->all();

            $merchant->users()->cursor()
                ->tapEach(function (MerchantUser $user) {
                    $user->tokens()->delete();
                    $user->forceDelete();
                })
                ->all();

            $merchant->checkouts()->delete();
            $merchant->customFields()->delete();
            $merchant->descriptionItems()->get()->each->delete();
            $merchant->emailBlasts()->get()->each->delete();
            $merchant->finances()->delete();
            $merchant->importBatches()->delete();
            $merchant->paymentTypes()->detach();
            $merchant->productGroups()->delete();
            $merchant->recurrences()->delete();
            $merchant->shippingMethods()->delete();
            $merchant->subscriptionCustomFields()->delete();
            $merchant->vouchers()->delete();
            $merchant->webhooks()->delete();
            $merchant->forceDelete();
        });
    }
}
