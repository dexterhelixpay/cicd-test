<?php

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetMembersPageButtonToMerchant_2022_09_09_004700 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Merchant::query()
            ->cursor()
            ->tapEach(function (Merchant $merchant) {
                DB::transaction(function () use ($merchant) {
                    $buttons = $merchant->buttons;

                    $merchant->update([
                        'buttons' => data_set(
                            $buttons,
                            'members_page_button',
                            [
                                'label' => 'Members Page',
                                'css' => [
                                    'background-color' => null
                                ]
                            ]
                        )
                    ]);
                });
            })
            ->all();
    }
}
