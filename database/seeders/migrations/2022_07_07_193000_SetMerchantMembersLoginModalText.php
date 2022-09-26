<?php

use App\Models\Merchant;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SetMerchantMembersLoginModalText_2022_07_07_193000 extends Seeder
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
                ->whereNull('deleted_at')
                ->with('merchant')
                ->cursor()
                ->tapEach(function (Merchant $merchant) {
                    $merchant->members_login_text = '<p>If you have a membership, we will send you a 6 digit OTP code to get access.</p><br><p>Don&apos;t have access yet? Buy a membership to gain access.</p>';
                    $merchant->saveQuietly();
            })->all();
        });
    }
}
