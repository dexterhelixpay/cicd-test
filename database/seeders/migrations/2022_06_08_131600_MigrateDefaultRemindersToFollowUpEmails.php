<?php

use App\Models\Merchant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateDefaultRemindersToFollowUpEmails_2022_06_08_131600 extends Seeder
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
                ->cursor()
                ->tapEach(function (Merchant $merchant) {
                    $defaults = [
                        '-3' => [
                            'headline' => $merchant->incoming_payment_headline_text
                                ?? 'Payment Due on {billingDate}',
                            'body' => $merchant->incoming_payment_subheader_text
                                ?? 'You will be charged automatically. No need to do anything. Enjoy!',
                            'subject' => $merchant->incoming_payment_subject_text
                                ?? 'Next Payment Due - {billingDate}',
                            'days' => '-3',
                            'is_enabled' => true
                        ],
                        '0' => [
                            'headline' => $merchant->due_payment_headline_text
                                ?? 'Payment Due Today!',
                            'body' => $merchant->due_payment_subheader_text
                                ?? 'Please pay today to {startOrContinue} your {subscriptionTermSingular}',
                            'subject' => $merchant->due_payment_subject_text
                                ?? 'Payment Due Today  - {billingDate}',
                            'days' => '0',
                            'is_enabled' => true
                        ],
                        '3' => [
                            'headline' => $merchant->late_payment_subject_text
                                ?? 'Complete your payment!',
                            'body' => $merchant->late_payment_subject_text
                                ?? 'Please pay today to {startOrContinue} your {subscriptionTermSingular}',
                            'subject' => $merchant->late_payment_subject_text
                                ?? 'Complete payment to {startOrContinue} {subscriptionTermSingular}',
                            'days' => '3',
                            'is_enabled' => true
                        ]
                    ];

                    collect($defaults)
                        ->each(function ($default, $day) use($merchant) {
                            $followUpEmail = $merchant->followUpEmails()
                                ->make($default);
                            $followUpEmail->save();
                        });
                })
                ->all();
        });
    }
}
