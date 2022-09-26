<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\Order;
use App\Models\PaymayaMid;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DbMockify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:mockify
        {--paymaya : Overwrite PayMaya keys}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Replace database values with mock data';

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
        if (!$this->confirm('This will replace all emails and mobile numbers to mock data. Continue?')) {
            return;
        }

        DB::transaction(function () {
            $index = 0;
            $philippines = Country::where('code', 'PH')->first();

            if ($this->option('paymaya')) {
                $vault = PaymayaMid::query()
                    ->where('is_vault', true)
                    ->where('public_key', config('services.paymaya.vault.public_key'))
                    ->where('secret_key', config('services.paymaya.vault.secret_key'))
                    ->first();

                Merchant::query()
                    ->whereNotNull('paymaya_vault_mid_id')
                    ->orWhereNotNull('paymaya_vault_public_key')
                    ->orWhereNotnull('paymaya_vault_secret_key')
                    ->update([
                        'paymaya_vault_mid_id' => optional($vault)->getKey(),
                        'paymaya_vault_public_key' => optional($vault)->getRawOriginal('public_key'),
                        'paymaya_vault_secret_key' => optional($vault)->getRawOriginal('secret_key'),
                    ]);

                $pwp = PaymayaMid::query()
                    ->where('is_pwp', true)
                    ->where('public_key', config('services.paymaya.pwp.public_key'))
                    ->where('secret_key', config('services.paymaya.pwp.secret_key'))
                    ->first();

                Merchant::query()
                    ->whereNotNull('paymaya_pwp_mid_id')
                    ->orWhereNotNull('paymaya_pwp_public_key')
                    ->orWhereNotnull('paymaya_pwp_secret_key')
                    ->update([
                        'paymaya_pwp_mid_id' => optional($pwp)->getKey(),
                        'paymaya_pwp_public_key' => optional($pwp)->getRawOriginal('public_key'),
                        'paymaya_pwp_secret_key' => optional($pwp)->getRawOriginal('secret_key'),
                    ]);
            }

            Subscription::query()
                ->whereNotNull('paymaya_payment_token_id')
                ->orWhereNotNull('paymaya_card_token_id')
                ->orWhereNotNull('paymaya_link_id')
                ->update([
                    'paymaya_payment_token_id' => null,
                    'paymaya_verification_url' => null,
                    'paymaya_card_token_id' => null,
                    'paymaya_card_type' => null,
                    'paymaya_masked_pan' => null,

                    'paymaya_link_id' => null,
                    'paymaya_wallet_customer_name' => null,
                    'paymaya_wallet_mobile_number' => null,
                ]);

            Order::query()
                ->whereNotNull('paymaya_card_token_id')
                ->orWhereNotNull('paymaya_link_id')
                ->update([
                    'paymaya_card_token_id' => null,
                    'paymaya_card_type' => null,
                    'paymaya_masked_pan' => null,

                    'paymaya_link_id' => null,
                ]);

            Merchant::query()->withTrashed()->cursor()
                ->tapEach(function (Merchant $merchant) use (&$index, $philippines) {
                    $merchant->updateQuietly([
                        'username' => null,
                        'email' => $merchant->email
                            ? $this->toEmail($index)
                            : null,
                        'mobile_number' => $merchant->mobile_number
                            ? $this->toMobileNumber($index)
                            : null,
                        'password' => null,
                    ]);

                    $merchant->users()->withTrashed()->cursor()
                        ->tapEach(function (MerchantUser $user) use (&$index) {
                            $user->updateQuietly([
                                'email' => $this->toEmail($index),
                                'mobile_number' => $user->mobile_number
                                    ? $this->toMobileNumber($index)
                                    : null,
                                'password' => $user->password
                                    ? bcrypt('demo1234')
                                    : null,
                            ]);

                            $index++;
                        })
                        ->all();

                    $merchant->customers()->withTrashed()->cursor()
                        ->tapEach(function (Customer $customer) use (&$index, $philippines) {
                            $customer->cards()->delete();
                            $customer->wallets()->delete();

                            $customer->updateQuietly([
                                'country_id' => $philippines->getKey(),
                                'email' => $customer->email
                                    ? $this->toEmail($index)
                                    : null,
                                'mobile_number' => $customer->mobile_number
                                    ? mb_substr($this->toMobileNumber($index), 1)
                                    : null,
                            ]);

                            $index++;
                        })
                        ->all();
                })
                ->all();
        });

        return 0;
    }

    /**
     * Convert the given number to a mobile number.
     *
     * @param  int  $number
     * @return string
     */
    protected function toMobileNumber($number)
    {
        $number = (string) $number;

        return '09' . str_pad($number, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Convert the given number to an email.
     *
     * @param  int  $number
     * @return string
     */
    protected function toEmail($number)
    {
        $username = Arr::random(['danilo', 'issa', 'rachelle']);

        return "{$username}+{$number}@goodwork.ph";
    }
}
