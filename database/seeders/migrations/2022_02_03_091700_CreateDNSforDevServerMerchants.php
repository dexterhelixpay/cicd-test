<?php

use App\Models\Merchant;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use App\Facades\Cloudflare\Zone;
use Illuminate\Support\Facades\DB;

class CreateDNSforDevServerMerchants_2022_02_03_091700 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Merchant::query()
                ->whereNotNull('subdomain')
                ->cursor()
                ->tapEach(function (Merchant $merchant)  {
                    $merchant->update([
                        'subdomain' => Str::replace('sandbox', 'dev', $merchant->subdomain)
                    ]);
                    Zone::createDnsRecord($merchant->subdomain, config('bukopay.ip.booking_site'));
                })
                ->all();
        });
    }
}
