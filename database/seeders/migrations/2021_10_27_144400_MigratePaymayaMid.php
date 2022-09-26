<?php

use App\Models\Merchant;
use App\Models\PaymayaMid;
use Illuminate\Database\Seeder;

class MigratePaymayaMid_2021_10_27_144400 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Merchant::query()
            ->whereNotNull('paymaya_vault_secret_key')
            ->orWhereNotNull('paymaya_pwp_secret_key')
            ->cursor()
            ->tapEach(function (Merchant $merchant) {
                if (
                    $merchant->paymaya_vault_mid_id
                    || $merchant->paymaya_vault_public_key
                    || $merchant->paymaya_vault_secret_key
                ) {
                    $mid = PaymayaMid::query()
                        ->where('is_vault', true)
                        ->where(function ($query) use ($merchant) {
                            $query
                                ->when($merchant->paymaya_vault_mid_id, function ($query, $key) {
                                    $query->orWhere('id', $key);
                                })
                                ->when($merchant->paymaya_vault_public_key, function ($query, $public) {
                                    $query->orWhere('public_key', $public);
                                })
                                ->when($merchant->paymaya_vault_secret_key, function ($query, $secret) {
                                    $query->orWhere('secret_key', $secret);
                                });
                        })
                        ->first();

                    if ($mid) {
                        $merchant->paymayaVaultMid()
                            ->associate($mid)
                            ->forceFill([
                                'paymaya_vault_public_key' => $mid->getRawOriginal('public_key'),
                                'paymaya_vault_secret_key' => $mid->getRawOriginal('secret_key'),
                            ]);
                    }
                }

                if (
                    $merchant->paymaya_pwp_mid_id
                    || $merchant->paymaya_pwp_public_key
                    || $merchant->paymaya_pwp_secret_key
                ) {
                    $mid = PaymayaMid::query()
                        ->where('is_pwp', true)
                        ->where(function ($query) use ($merchant) {
                            $query
                                ->when($merchant->paymaya_pwp_mid_id, function ($query, $key) {
                                    $query->orWhere('id', $key);
                                })
                                ->when($merchant->paymaya_pwp_public_key, function ($query, $public) {
                                    $query->orWhere('public_key', $public);
                                })
                                ->when($merchant->paymaya_pwp_secret_key, function ($query, $secret) {
                                    $query->orWhere('secret_key', $secret);
                                });
                        })
                        ->first();

                    if ($mid) {
                        $merchant->paymayaPwpMid()
                            ->associate($mid)
                            ->forceFill([
                                'paymaya_pwp_public_key' => $mid->getRawOriginal('public_key'),
                                'paymaya_pwp_secret_key' => $mid->getRawOriginal('secret_key'),
                            ]);
                    }
                }

                $merchant->saveQuietly();
                $merchant->refresh();

                if ($merchant->paymaya_vault_mid_id && !$merchant->paymaya_pwp_mid_id) {
                    $mid = PaymayaMid::query()
                        ->where('is_pwp', true)
                        ->where('business_segment', 'All / Others')
                        ->first();

                    if ($mid) {
                        $merchant->paymayaPwpMid()
                            ->associate($mid)
                            ->forceFill([
                                'paymaya_pwp_public_key' => $mid->getRawOriginal('public_key'),
                                'paymaya_pwp_secret_key' => $mid->getRawOriginal('secret_key'),
                            ])
                            ->saveQuietly();
                    }
                }
            })
            ->all();
    }
}
