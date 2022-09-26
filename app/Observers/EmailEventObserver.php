<?php

namespace App\Observers;

use App\Models\Email;
use App\Models\EmailEvent;
use Illuminate\Support\Str;

class EmailEventObserver
{
    /**
     * Handle the Email Event "created" event.
     *
     * @param  \App\Models\EmailEvent  $emailEvent
     * @return void
     */
    public function created($emailEvent)
    {
        $this->calculateRates($emailEvent);
    }

    /**
     * Calculate rates
     *
     * @param  \App\Models\EmailEvent  $emailEvent
     * @return void
     */
    protected function calculateRates(EmailEvent $emailEvent)
    {
        if (in_array($event = $emailEvent->event, ['dropped'])) {
            return;
        }

        if (!$email = $emailEvent->email()->first()) {
            return;
        }

        $email->fill(["{$event}_count" => ($email->{"{$event}_count"} ?: 0) + 1]);

        if (in_array($event, ['click', 'open', 'delivered'])) {
            $email["unique_{$event}_count"] = $email->events()
                ->where('event', $event)
                ->distinct('email_address')
                ->count();
        }

        if ($email->unique_delivered_count && in_array($event, ['click', 'open'])) {
            $rate = ($email["unique_{$event}_count"] / $email->unique_delivered_count) * 100;
            $email["{$event}_rate"] = $rate > 100 ? 100 : $rate;
        }

        $email->update();
    }

}
