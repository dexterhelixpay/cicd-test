<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Notifications\EmailBlastNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailBlast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The email blast model.
     *
     * @var \App\Models\MerchantEmailBlast
     */
    protected $emailBlast;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\MerchantEmailBlast  $emailBlast
     * @return void
     */
    public function __construct($emailBlast)
    {
        $this->emailBlast = $emailBlast;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        collect($this->emailBlast->targeted_customer_ids ?? [])
            ->unique()
            ->each(function ($customerId) {
                $customer = Customer::find($customerId);

                if (!optional($customer)->email) {
                    return;
                }

                $customer->notify(new EmailBlastNotification(
                    $this->emailBlast,
                    $this->emailBlast->merchant,
                    $customer
                ));
            })
            ->all();
    }
}
