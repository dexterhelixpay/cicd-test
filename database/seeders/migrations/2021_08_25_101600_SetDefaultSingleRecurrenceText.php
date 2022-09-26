<?php

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetDefaultSingleRecurrenceText_2021_08_25_101600 extends Seeder
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
            ->whereNull('single_recurrence_title')
            ->update([
                'single_recurrence_title' => 'Try a Subscription?',
                'single_recurrence_subtitle' => 'You can easily change your order to a subscription!',
                'single_recurrence_button_text' => 'Create a Subscription'
            ]);
        });
    }
}
