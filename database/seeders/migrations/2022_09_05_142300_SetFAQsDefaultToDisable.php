<?php

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetFAQsDefaultToDisable_2022_09_05_142300 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
            $faqs = "
            <h1><b>How do subscriptions work?</b></h1> <p>Subscriptions ensure you never run
            out and don't have to worry about remembering to order. With a subscription,
            monthly payments and deliveries are automatic so it's super easy.</p><h1><b>How
            will I be able to manage my subscription?</b></h1> <p>You'll be able to edit
            the payment method and change the inclusions of your order on the email you'll
            receive every month.</p><h1><b>When will I receive my order for the month?</b></h1>
            <p>Deliveries will be processed soon after your payment has been made each month.
            The shipping costs are already included in your billing summary.</p><h1><b>What
            happens when I am not at home to receive my package but it's been paid for
            already?</b></h1> <p>We work with top courier services to ensure that your
            products arrive safely at your door. Drivers normally give you a call when
            they are on their way.</p><h1><b>How can I pay?</b></h1> <p>For credit/debit
            card payments, your account will automatically get charged each month and you
            won't have to do anything. Bank transfer will be processed once you
            proceed with the payment flow each month. You will get an email 3 days before you
            will be charged for card payments to notify you of the billing summary while you will
            get an email reminder to proceed with payment for bank transfers.</p><h1><b>Can I trust
            you with my card details?</b></h1> <p>Your payment details are encrypted and stored by top
            payment technology providers. We do not have access to any of your payment details. The only
            information we have is the payment method you've selected.</p><h1><b>Where can I contact you
            if I have concerns about my subscription?</b></h1> <p>Send us a message on our
            <a href='https://www.facebook.com/BukoPay.ph/' target='_blank'>Facebook</a>
            page and we'll respond as soon as we can.</p>";

        Merchant::query()
            ->where('faqs', $faqs)
            ->cursor()
            ->each(function (Merchant $merchant) {
                DB::transaction(function () use ($merchant){
                    $merchant->update([
                        'is_faqs_enabled' => false
                    ]);
                });
            })->all();
    }
}
